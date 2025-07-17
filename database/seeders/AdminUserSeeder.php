<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ApprovalRank;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder {
    public function run(): void {
        $mainBranch = Branch::first();
        $adminRank = ApprovalRank::where('rank_level', 0)->first();
        $defaultGroup = Group::first();

        if (!$mainBranch || !$adminRank || !$defaultGroup) {
            $this->command->error('Vui lòng chạy các seeder cho Branch, ApprovalRank, và Group trước!');
            return;
        }

        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'prs_id' => 'ADMIN001',
            'employee_id' => 'ADMIN_EMPLOYEE',
            'password' => Hash::make('12345678'),
            'main_branch_id' => $mainBranch->id,
            'status' => true,
        ]);

        Assignment::create([
            'user_id' => $adminUser->id,
            'branch_id' => $mainBranch->id,
            'approval_rank_id' => $adminRank->id,
            'group_id' => $defaultGroup->id,
        ]);
    }
}
