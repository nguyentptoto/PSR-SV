<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobTitle;

class JobTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Danh sách các chức danh cần tạo
        $jobTitles = [
            'AGM',
            'AM',
            'AM(TT)',
            'DA',
            'DGM',
            'DMG',
            'DMG(TT)',
            'DR',
            'FM',
            'FM(TT)',
            'GD',
            'GM',
            'IP',
            'MG',
            'SF',
            'SF(TT)',
            'SV',
            'SV(TT)',
            'VDR',
            'WK'
        ];

        // Vòng lặp qua danh sách và tạo chức danh nếu chưa tồn tại
        // Sử dụng firstOrCreate để tránh lỗi 'Duplicate entry' khi chạy seed nhiều lần
        foreach ($jobTitles as $title) {
            JobTitle::firstOrCreate(['name' => $title]);
        }
    }
}
