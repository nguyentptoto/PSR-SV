<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder {
    public function run(): void {
        DB::table('branches')->insert([
            ['name' => 'Hưng Yên', 'address' => 'Hưng Yên', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Đông Anh', 'address' => 'Đông Anh, Hà Nội', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vĩnh Phúc', 'address' => 'Vĩnh Phúc', 'created_at' => now(), 'updated_at' => now()],

        ]);
    }
}
