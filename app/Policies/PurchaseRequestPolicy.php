<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Xác định xem người dùng có thể duyệt một phiếu đề nghị cụ thể hay không.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PurchaseRequest  $purchaseRequest
     * @return bool
     */
    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        // Không cho phép duyệt các phiếu đã hoàn thành hoặc bị từ chối
        if (in_array($purchaseRequest->status, ['completed', 'rejected'])) {
            return false;
        }

        $isRequestingStage = $purchaseRequest->status === 'pending_approval';
        $isPurchasingStage = $purchaseRequest->status === 'purchasing_approval';

        // Kiểm tra xem người dùng có một bản phân công nào khớp chính xác với trạng thái hiện tại của phiếu không
        return $user->assignments->contains(function ($assignment) use ($user, $purchaseRequest, $isRequestingStage, $isPurchasingStage) {

            // Điều kiện 1: Cấp bậc và Chi nhánh phải khớp
            if ($assignment->approvalRank->rank_level != $purchaseRequest->current_rank_level || $assignment->branch_id != $purchaseRequest->branch_id) {
                return false;
            }

            // Điều kiện 2: Nhóm chức năng phải khớp với giai đoạn của phiếu
            if ($isRequestingStage && $assignment->group->name === 'Phòng Đề Nghị') {
                // Nếu là Cấp 4, phải có cờ yêu cầu duyệt
                if ($assignment->approvalRank->rank_level == 4 && !$purchaseRequest->requires_director_approval) {
                    return false;
                }
                // Nếu là cấp dưới giám đốc, phải quản lý cùng phòng ban
                if ($assignment->approvalRank->rank_level < 4) {
                    return $user->sections()->where('sections.id', $purchaseRequest->section_id)->exists();
                }
                return true; // Giám đốc không cần chung phòng ban
            }

            if ($isPurchasingStage && $assignment->group->name === 'Phòng Mua') {
                return true; // Phòng mua không cần lọc theo phòng ban
            }

            return false;
        });
    }
}
