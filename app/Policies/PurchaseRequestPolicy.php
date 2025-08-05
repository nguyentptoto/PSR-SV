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
        // ✅ QUY TẮC MỚI: Cho phép duyệt nếu người dùng là người được phân công
        if ($purchaseRequest->assigned_purchaser_id === $user->id) {
            return true;
        }
        $isRequestingStage = $purchaseRequest->status === 'pending_approval';
        $isPurchasingStage = $purchaseRequest->status === 'purchasing_approval';

        // Kiểm tra xem người dùng có một bản phân công nào khớp chính xác với trạng thái hiện tại của phiếu không
        return $user->assignments->contains(function ($assignment) use ($user, $purchaseRequest, $isRequestingStage, $isPurchasingStage) {
            // Điều kiện 1: Cấp bậc và Chi nhánh phải khớp
            if ($assignment->approvalRank->rank_level != $purchaseRequest->current_rank_level || $assignment->branch_id != $purchaseRequest->branch_id) {
                return false;
            }
            if ($isRequestingStage && $assignment->group->name === 'Phòng Đề Nghị') {
                // Nếu là Cấp 4, phải có cờ yêu cầu duyệt
                if ($assignment->approvalRank->rank_level == 4 && !$purchaseRequest->requires_director_approval) {
                    return false;
                }
                // ✅ ĐÃ SỬA: Bất kỳ cấp nào ở Phòng Đề Nghị cũng phải chung phòng ban
                if ($assignment->approvalRank->rank_level <= 4) {
                    return $user->sections()->where('sections.id', $purchaseRequest->section_id)->exists();
                }
                return true; // Dòng này có thể không bao giờ được gọi đến nữa, nhưng giữ lại cũng không sao
            }
            if ($isPurchasingStage && $assignment->group->name === 'Phòng Mua') {
                return true;
            }
            return false;
        });
    }
}
