<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'branch_id',
        'approval_rank_id',
        'group_id',
    ];

    /**
     * ✅ ĐÃ SỬA LỖI: Đổi tên phương thức từ 'rank' thành 'approvalRank'
     * để khớp với code trong file Blade của bạn.
     *
     * Định nghĩa mối quan hệ "thuộc về" với ApprovalRank.
     */
    public function approvalRank(): BelongsTo
    {
        return $this->belongsTo(ApprovalRank::class, 'approval_rank_id');
    }

    /**
     * Định nghĩa mối quan hệ "thuộc về" với User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Định nghĩa mối quan hệ "thuộc về" với Branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Định nghĩa mối quan hệ "thuộc về" với Group.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
