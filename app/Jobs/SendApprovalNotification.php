<?php

namespace App\Jobs;

use App\Mail\PurchaseRequestNotification;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $purchaseRequest;
    protected $recipients;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\PurchaseRequest  $purchaseRequest
     * @param  \Illuminate\Support\Collection  $recipients
     * @return void
     */
    public function __construct(PurchaseRequest $purchaseRequest, Collection $recipients)
    {
        $this->purchaseRequest = $purchaseRequest;
        $this->recipients = $recipients;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Lặp qua danh sách người nhận và gửi email
        foreach ($this->recipients as $recipient) {
            // Đảm bảo người nhận là một đối tượng User hợp lệ và có email
            if ($recipient instanceof User && !empty($recipient->email)) {
                Mail::to($recipient->email)->send(new PurchaseRequestNotification($this->purchaseRequest));
            }
        }
    }
}
