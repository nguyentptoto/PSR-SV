<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Assignment;
use App\Models\Branch;
use App\Models\JobTitle;
use App\Models\ApprovalRank;
use App\Models\Group;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        User::truncate();
        Assignment::truncate();
        Schema::enableForeignKeyConstraints();

        try {
            // Lấy các ID cần thiết.
            // Sửa lại để lấy bản ghi ĐẦU TIÊN tìm thấy, giúp seeder linh hoạt hơn.
            // Điều này sẽ dùng 'Hưng Yên' làm chi nhánh và 'AGM' làm chức danh dựa trên các seeder bạn cung cấp.
            $mainBranchId = Branch::firstOrFail()->id;
            $adminJobTitleId = JobTitle::firstOrFail()->id;

            // Giữ nguyên logic tìm kiếm cho các model có dữ liệu không thay đổi
            $adminRankId = ApprovalRank::where('rank_level', 0)->firstOrFail()->id;
            $requestingGroupId = Group::where('name', 'Phòng Đề Nghị')->firstOrFail()->id;

        } catch (ModelNotFoundException $e) {
            // Nếu một trong các lệnh trên thất bại, chúng ta sẽ bắt lỗi ở đây.
            $modelClass = $e->getModel(); // Lấy tên model bị lỗi, ví dụ: "App\Models\Branch"

            // In ra thông báo lỗi rõ ràng cho người dùng
            $this->command->error("LỖI QUAN TRỌNG KHI CHẠY UserSeeder:");
            $this->command->error("Không thể tìm thấy dữ liệu cần thiết từ model: {$modelClass}.");
            $this->command->info("=> Gợi ý: Hãy kiểm tra lại file Seeder tương ứng (ví dụ: BranchSeeder.php, JobTitleSeeder.php...)");
            $this->command->info("   và đảm bảo rằng nó đã tạo ra bản ghi mà UserSeeder đang tìm kiếm.");

            // Dừng seeder lại để tránh các lỗi phát sinh thêm
            return;
        }

        // Nếu code chạy được đến đây, nghĩa là tất cả ID đã được tìm thấy thành công.
        // --- Tạo tài khoản Admin ---
        $admin = User::create([
            'name' => 'Admin User', // Đổi tên để phân biệt
            'email' => 'admin@example.com',
            'employee_id' => 'ADMIN',
            'password' => Hash::make('12345678'),
            'main_branch_id' => $mainBranchId,
            'job_title_id' => $adminJobTitleId,
        ]);

        // Tạo bản ghi assignment cho Admin
        Assignment::create([
            'user_id' => $admin->id,
            'branch_id' => $mainBranchId,
            'approval_rank_id' => $adminRankId,
            'group_id' => $requestingGroupId,
        ]);

        $this->command->info('Tài khoản Admin và Assignment đã được tạo thành công.');
    }
}
