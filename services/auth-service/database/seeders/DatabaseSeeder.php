<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'admin', 'description' => 'Quan tri vien'],
            ['id' => 2, 'name' => 'staff', 'description' => 'Nhan vien'],
            ['id' => 3, 'name' => 'customer', 'description' => 'Khach hang'],
        ]);
    }
}
