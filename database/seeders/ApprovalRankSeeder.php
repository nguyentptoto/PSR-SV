<?php
namespace Database\Seeders;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Seeder;
use App\Models\ApprovalRank;

class ApprovalRankSeeder extends Seeder
{
    public function run(): void
    {
          // Vô hiệu hóa kiểm tra khóa ngoại
        Schema::disableForeignKeyConstraints();

        // Xóa toàn bộ dữ liệu cũ trong bảng
        DB::table('approval_ranks')->truncate();

        // Bật lại kiểm tra khóa ngoại
        Schema::enableForeignKeyConstraints();
        // Tạo các bản ghi ApprovalRank mới
        ApprovalRank::create(['name' => 'Admin', 'rank_level' => 0]);
        ApprovalRank::create(['name' => 'Cấp 1', 'rank_level' => 1]);
        ApprovalRank::create(['name' => 'Cấp 2', 'rank_level' => 2]);
        ApprovalRank::create(['name' => 'Cấp 3', 'rank_level' => 3]);
        ApprovalRank::create(['name' => 'Cấp 4', 'rank_level' => 4]);
    }
}
