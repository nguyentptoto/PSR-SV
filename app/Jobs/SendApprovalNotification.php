<?php

namespace App\Jobs;

use App\Mail\PurchaseRequestNotification;
use App\Models\PurchaseRequest;
use App\Models\User; // Quan trọng
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \App\Models\PurchaseRequest
     */
    protected $purchaseRequest;

    /**
     * @var \App\Models\User
     */
    protected $recipient; // <-- Sửa từ $recipients thành $recipient

    /**
     * Create a new job instance.
     *
     * @param \App\Models\PurchaseRequest $purchaseRequest
     * @param \App\Models\User $recipient // <-- Chỉ nhận một User
     * @return void
     */
    public function __construct(PurchaseRequest $purchaseRequest, User $recipient)
    {
        $this->purchaseRequest = $purchaseRequest;
        $this->recipient = $recipient;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
   public function handle(): void
{
    Mail::to($this->recipient->email)
        ->send(new PurchaseRequestNotification($this->purchaseRequest));
}
}
