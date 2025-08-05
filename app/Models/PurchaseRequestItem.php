<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    use HasFactory;
    // Thay thế protected $guarded = []; bằng protected $fillable
    protected $fillable = [
        'purchase_request_id',
        'item_code',
        'item_name',
        'old_item_code',
        'order_quantity',
        'order_unit',
        'inventory_quantity',
        'inventory_unit',
        'r3_price',
        'estimated_price',
        'subtotal',
        'using_dept_code',
        'plant_system',
<<<<<<< HEAD
       
=======
        // 'purchase_group',   // Cột mới từ PGr
        // 'legacy_item_code', // Cột mới từ A (Cột B)
>>>>>>> 008a4b41ca5eda2e1bb01a13d8f90c7b4f76a3ab
    ];

    protected $casts = [
        'order_quantity' => 'decimal:3',
        'inventory_quantity' => 'decimal:3',
        'r3_price' => 'decimal:2',
        'estimated_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
