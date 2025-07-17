<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Xác định xem người dùng có thể xem danh sách người dùng không.
     * Chỉ admin mới có quyền này.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Xác định xem người dùng có thể xem thông tin của một người dùng khác không.
     */
    public function view(User $currentUser, User $targetUser): bool
    {
        // Admin có thể xem bất kỳ người dùng nào.
        if ($this->isAdmin($currentUser)) {
            return true;
        }

        // Người dùng thường chỉ có thể xem thông tin của chính họ.
        return $currentUser->id === $targetUser->id;
    }

    /**
     * Xác định xem người dùng có thể tạo người dùng mới không.
     * Chỉ admin mới có quyền này.
     */
    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Xác định xem người dùng có thể cập nhật thông tin người dùng không.
     */
    public function update(User $currentUser, User $targetUser): bool
    {
        // Admin có thể cập nhật bất kỳ người dùng nào.
        if ($this->isAdmin($currentUser)) {
            return true;
        }

        // Người dùng thường chỉ có thể cập nhật thông tin của chính họ.
        return $currentUser->id === $targetUser->id;
    }

    /**
     * Xác định xem người dùng có thể xóa người dùng không.
     * Chỉ admin mới có quyền này.
     */
    public function delete(User $currentUser, User $targetUser): bool
    {
        // Chỉ Admin mới có quyền xóa người dùng.
        return $this->isAdmin($currentUser);
    }

    /**
     * Hàm private để kiểm tra quyền admin.
     */
    private function isAdmin(User $user): bool
    {
        // Sử dụng đúng tên relationship là 'approvalRank'.
        return $user->assignments()->whereHas('approvalRank', function ($query) {
            $query->where('rank_level', 0);
        })->exists();
    }
}
