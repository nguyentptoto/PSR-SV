<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupSeeder extends Seeder {
    public function run(): void {
        DB::table('groups')->insert([
            ['name' => 'Phòng Đề Nghị'],
            ['name' => 'Phòng Mua'],
        ]);
    }
}