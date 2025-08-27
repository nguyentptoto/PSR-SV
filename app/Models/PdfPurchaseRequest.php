<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfPurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'pia_code',
        'requester_id',
        'original_pdf_path',
        'signed_pdf_path',
        'attachment_path', // <-- THÊM DÒNG NÀY

        'status',
        'remarks',
        'signature_pos_x',
        'signature_pos_y',
        'signature_width',
        'signature_height',
        'signature_page',
        'current_rank_level', // <-- THÊM DÒNG NÀY
        'requires_director_approval', // <-- THÊM DÒNG NÀY

    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
 public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistory::class, 'pdf_purchase_request_id');
    }
    // Bạn có thể thêm các mối quan hệ hoặc accessor khác nếu cần
}
