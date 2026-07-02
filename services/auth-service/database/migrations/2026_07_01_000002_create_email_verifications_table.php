<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addedEmailVerifiedAt = false;

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable();
            });

            $addedEmailVerifiedAt = true;
        }

        if (! Schema::hasTable('email_verifications')) {
            Schema::create('email_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('email', 191)->index();
                $table->string('otp_code');
                $table->string('type', 30)->index();
                $table->timestamp('expires_at')->index();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();

                $table->index(['email', 'type', 'expires_at']);
            });
        }

        if ($addedEmailVerifiedAt) {
            DB::table('users')
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('email_verified_at');
            });
        }
    }
};
