<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $this->addUserColumn('address', fn (Blueprint $table) => $table->string('address', 500)->nullable());
            $this->addUserColumn('province', fn (Blueprint $table) => $table->string('province', 100)->nullable());
            $this->addUserColumn('district', fn (Blueprint $table) => $table->string('district', 100)->nullable());
            $this->addUserColumn('ward', fn (Blueprint $table) => $table->string('ward', 100)->nullable());
            $this->addUserColumn('default_shipping_address', fn (Blueprint $table) => $table->text('default_shipping_address')->nullable());
            $this->addUserColumn('gender', fn (Blueprint $table) => $table->string('gender', 20)->nullable());
            $this->addUserColumn('date_of_birth', fn (Blueprint $table) => $table->date('date_of_birth')->nullable());
            $this->addUserColumn('last_login_at', fn (Blueprint $table) => $table->timestamp('last_login_at')->nullable());
            $this->addUserColumn('created_at', fn (Blueprint $table) => $table->timestamp('created_at')->nullable());
        }

        if (! Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('receiver_name', 100);
                $table->string('receiver_phone', 30);
                $table->string('receiver_email', 191)->nullable();
                $table->string('address', 500);
                $table->string('province', 100)->nullable();
                $table->string('district', 100)->nullable();
                $table->string('ward', 100)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');

        foreach ([
            'address',
            'province',
            'district',
            'ward',
            'default_shipping_address',
            'gender',
            'date_of_birth',
            'last_login_at',
            'created_at',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    private function addUserColumn(string $column, callable $definition): void
    {
        if (! Schema::hasColumn('users', $column)) {
            Schema::table('users', fn (Blueprint $table) => $definition($table));
        }
    }
};
