<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'sap_release_date' => 'date',
        'requested_delivery_date' => 'date',
        'requires_director_approval' => 'boolean',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function executingDepartment()
    {
        return $this->belongsTo(ExecutingDepartment::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistory::class);
    }

    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case 'pending_approval':
                return 'Chờ duyệt';
            case 'purchasing_approval': // <-- Đã xóa khoảng trắng thừa ở đây
                return 'Phòng Mua duyệt';
            case 'completed':
                return 'Hoàn thành';
            case 'rejected':
                return 'Bị từ chối';
            default:
                return 'Không xác định';
        }
    }

    public function getStatusClassAttribute()
    {
        switch ($this->status) {
            case 'pending_approval':
                return 'bg-warning text-dark';
            case 'purchasing_approval': // <-- Đã xóa khoảng trắng thừa ở đây
                return 'bg-info text-dark';
            case 'completed':
                return 'bg-success';
            case 'rejected':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
}
