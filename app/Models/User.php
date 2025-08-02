<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // 'password' => 'hashed',
    ];

    /**
     * Get the user's main branch.
     */
    public function mainBranch()
    {
        return $this->belongsTo(Branch::class, 'main_branch_id');
    }

    /**
     * Get the user's job title.
     */
    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * The sections that belong to the user.
     */
    public function sections()
    {
        return $this->belongsToMany(Section::class, 'user_section');
    }

    /**
     * Get all of the assignments for the user.
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get all of the purchase requests created by the user.
     */
    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class, 'requester_id');
    }

    /**
     * Get all of the approval histories for the user.
     */
    public function approvalHistories()
    {
        return $this->hasMany(ApprovalHistory::class);
    }
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Lấy danh sách các nhân viên cấp dưới.
     */
    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
    }
     public function pdfPurchaseRequests() // <-- THÊM MỐI QUAN HỆ NÀY
    {
        return $this->hasMany(PdfPurchaseRequest::class, 'requester_id');
    }
}
