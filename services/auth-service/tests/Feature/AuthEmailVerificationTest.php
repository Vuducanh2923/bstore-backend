<?php

use App\Mail\ForgotPasswordOtpMail;
use App\Mail\RegisterOtpMail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'database.connections.bstore_auth' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    DB::purge('bstore_auth');

    Schema::connection('bstore_auth')->create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100)->unique();
        $table->text('description')->nullable();
    });

    Schema::connection('bstore_auth')->create('users', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('role_id')->nullable();
        $table->string('full_name', 191);
        $table->string('email', 191)->unique();
        $table->dateTime('email_verified_at')->nullable();
        $table->string('password');
        $table->string('phone', 30)->nullable();
        $table->string('status', 50)->nullable()->default('active');
        $table->dateTime('last_login_at')->nullable();
        $table->dateTime('created_at')->nullable();
    });

    Schema::connection('bstore_auth')->create('email_verifications', function (Blueprint $table) {
        $table->id();
        $table->string('email', 191)->index();
        $table->string('otp_code');
        $table->string('type', 30)->index();
        $table->dateTime('expires_at')->index();
        $table->dateTime('verified_at')->nullable();
        $table->timestamps();
    });

    DB::connection('bstore_auth')->table('roles')->insert([
        ['id' => 1, 'name' => User::ROLE_ADMIN, 'description' => 'Quan tri vien'],
        ['id' => 2, 'name' => User::ROLE_STAFF, 'description' => 'Nhan vien'],
        ['id' => 3, 'name' => User::ROLE_CUSTOMER, 'description' => 'Khach hang'],
    ]);
});

function createAuthEmailVerificationUser(array $overrides = []): User
{
    $user = new User;
    $user->forceFill([
        'role_id' => $overrides['role_id'] ?? 3,
        'full_name' => $overrides['full_name'] ?? 'Verified Customer',
        'email' => $overrides['email'] ?? 'verified@example.com',
        'email_verified_at' => $overrides['email_verified_at'] ?? now(),
        'password' => $overrides['password'] ?? Hash::make('secret123'),
        'status' => $overrides['status'] ?? 'active',
    ]);
    $user->save();

    return $user->load('role');
}

test('registered customer must verify email otp before login', function () {
    Mail::fake();

    $this->postJson('/api/auth/register', [
        'full_name' => 'Buyer One',
        'email' => 'buyer@example.com',
        'password' => 'secret123',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $user = User::where('email', 'buyer@example.com')->firstOrFail();

    expect($user->email_verified_at)->toBeNull();

    $otpCode = null;
    Mail::assertQueued(RegisterOtpMail::class, function (RegisterOtpMail $mail) use (&$otpCode) {
        $otpCode = $mail->otpCode;

        return true;
    });

    expect($otpCode)->toMatch('/^\d{6}$/');

    $this->postJson('/api/auth/login', [
        'email' => 'buyer@example.com',
        'password' => 'secret123',
    ])->assertForbidden();

    $this->postJson('/api/auth/verify-register-otp', [
        'email' => 'buyer@example.com',
        'otp_code' => $otpCode,
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect(DB::connection('bstore_auth')->table('email_verifications')
        ->where('email', 'buyer@example.com')
        ->where('type', 'register')
        ->count())->toBe(0);

    $this->postJson('/api/auth/login', [
        'email' => 'buyer@example.com',
        'password' => 'secret123',
    ])
        ->assertOk()
        ->assertJsonPath('data.email', 'buyer@example.com')
        ->assertJsonStructure(['data' => ['token']]);
});

test('forgot password otp can reset password with bcrypt hash', function () {
    Mail::fake();
    $user = createAuthEmailVerificationUser([
        'email' => 'forgot@example.com',
        'password' => Hash::make('old-secret'),
    ]);

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'forgot@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $otpCode = null;
    Mail::assertQueued(ForgotPasswordOtpMail::class, function (ForgotPasswordOtpMail $mail) use (&$otpCode) {
        $otpCode = $mail->otpCode;

        return true;
    });

    expect($otpCode)->toMatch('/^\d{6}$/');

    $verification = DB::connection('bstore_auth')->table('email_verifications')
        ->where('email', 'forgot@example.com')
        ->first();

    expect($verification)->not->toBeNull()
        ->and($verification->type)->toBe('forgot_password')
        ->and($verification->otp_code)->not->toBe($otpCode)
        ->and($verification->expires_at)->not->toBeNull()
        ->and(Carbon::parse($verification->expires_at)->isFuture())->toBeTrue();

    $this->postJson('/api/auth/verify-forgot-password-otp', [
        'email' => 'forgot@example.com',
        'otp_code' => $otpCode,
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(DB::connection('bstore_auth')->table('email_verifications')
        ->where('email', 'forgot@example.com')
        ->where('type', 'forgot_password')
        ->count())->toBe(1);

    $this->postJson('/api/auth/reset-password', [
        'email' => 'forgot@example.com',
        'otp_code' => $otpCode,
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('new-secret', $user->fresh()->password))->toBeTrue()
        ->and(DB::connection('bstore_auth')->table('email_verifications')->where('email', 'forgot@example.com')->count())->toBe(0);
});

test('wrong forgot password otp is not deleted', function () {
    $user = createAuthEmailVerificationUser([
        'email' => 'wrong-otp@example.com',
        'password' => Hash::make('old-secret'),
    ]);

    DB::connection('bstore_auth')->table('email_verifications')->insert([
        'email' => 'wrong-otp@example.com',
        'otp_code' => Hash::make('123456'),
        'type' => 'forgot_password',
        'expires_at' => now()->addMinutes(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson('/api/auth/reset-password', [
        'email' => 'wrong-otp@example.com',
        'otp_code' => '000000',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertUnprocessable();

    expect(Hash::check('old-secret', $user->fresh()->password))->toBeTrue()
        ->and(DB::connection('bstore_auth')->table('email_verifications')->where('email', 'wrong-otp@example.com')->count())->toBe(1);
});

test('expired forgot password otp is not deleted by reset password', function () {
    $user = createAuthEmailVerificationUser([
        'email' => 'expired-otp@example.com',
        'password' => Hash::make('old-secret'),
    ]);

    DB::connection('bstore_auth')->table('email_verifications')->insert([
        'email' => 'expired-otp@example.com',
        'otp_code' => Hash::make('123456'),
        'type' => 'forgot_password',
        'expires_at' => now()->subMinute(),
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    $this->postJson('/api/auth/reset-password', [
        'email' => 'expired-otp@example.com',
        'otp_code' => '123456',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertUnprocessable();

    expect(Hash::check('old-secret', $user->fresh()->password))->toBeTrue()
        ->and(DB::connection('bstore_auth')->table('email_verifications')->where('email', 'expired-otp@example.com')->count())->toBe(1);
});
