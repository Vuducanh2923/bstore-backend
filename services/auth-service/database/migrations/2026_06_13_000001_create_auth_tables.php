<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Áp dụng thay đổi cấu trúc cơ sở dữ liệu.
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('name', 100)->unique();
                $table->text('description')->nullable();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('full_name', 191);
                $table->string('email', 191)->unique();
                $table->string('password');
                $table->string('phone', 30)->nullable()->unique();
                $table->string('address', 500)->nullable();
                $table->string('province', 100)->nullable();
                $table->string('district', 100)->nullable();
                $table->string('ward', 100)->nullable();
                $table->text('default_shipping_address')->nullable();
                $table->string('gender', 20)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('avatar', 500)->nullable();
                $table->string('status', 50)->nullable()->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('receiver_name', 100);
                $table->string('receiver_phone', 30);
                $table->string('receiver_email', 191)->nullable();
                $table->string('address', 500);
                $table->string('province', 100)->nullable();
                $table->string('district', 100)->nullable();
                $table->string('ward', 100)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    // Hoàn tác thay đổi cấu trúc cơ sở dữ liệu.
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
