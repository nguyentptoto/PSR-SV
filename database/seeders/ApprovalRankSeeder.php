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
        // Schema::disableForeignKeyConstraints();

        // DB::table('approval_ranks')->truncate(); // Bây giờ lệnh này sẽ chạy được

        // Bật lại kiểm tra khóa ngoại
        // Schema::enableForeignKeyConstraints();
        ApprovalRank::create(['name' => 'Admin', 'rank_level' => 0]);
        ApprovalRank::create(['name' => 'Requester', 'rank_level' => 1]);
        ApprovalRank::create(['name' => 'Manager', 'rank_level' => 2]);
        ApprovalRank::create(['name' => 'General Manager', 'rank_level' => 3]);
        ApprovalRank::create(['name' => 'Director', 'rank_level' => 4]);
    }
}
