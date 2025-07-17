<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use HasFactory;
    // Thay thế protected $guarded = []; bằng protected $fillable
    protected $fillable = [
        'pia_code',
        'requester_id',
        'section_id',
        'executing_department_id',
        'branch_id',
        'sap_release_date',
        'requested_delivery_date',
        'currency',
        'total_amount',
        'total_order_quantity',
        'total_inventory_quantity',
        'status',
        'priority',
        'remarks',
        'attachment_path',
        'rejection_reason',
        'current_rank_level',
        'requires_director_approval',
        'sap_request_date', // Cột mới từ Req.Date
        'po_number',        // Cột mới từ PO
        'po_date',          // Cột mới từ PO Date
        'sap_created_by',   // Cột mới từ Created
    ];

    protected $casts = [
        'sap_release_date' => 'date',
        'requested_delivery_date' => 'date',
        'requires_director_approval' => 'boolean',
        // THÊM CAST CHO CÁC CỘT DATE MỚI
        'sap_request_date' => 'date',
        'po_date' => 'date',
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
            case 'purchasing_approval':
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
            case 'purchasing_approval':
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
