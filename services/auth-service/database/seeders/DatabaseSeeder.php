<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'ADMIN', 'description' => 'Quan tri vien'],
            ['id' => 2, 'name' => 'STAFF', 'description' => 'Nhan vien'],
            ['id' => 3, 'name' => 'CUSTOMER', 'description' => 'Khach hang'],
        ]);
    }
}
