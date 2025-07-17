<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            SectionSeeder::class,
            JobTitleSeeder::class,
            GroupSeeder::class,
            ApprovalRankSeeder::class,
            ExecutingDepartmentSeeder::class,
            UserSeeder::class, // Nên chạy sau cùng để có đủ dữ liệu từ các bảng khác
        ]);
    }
}
