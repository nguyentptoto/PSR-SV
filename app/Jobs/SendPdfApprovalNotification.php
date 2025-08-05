<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PdfPurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\PurchaseRequestNotification; // SỬA LẠI USE STATEMENT

class SendPdfApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pdfPurchaseRequest;
    protected $approver;

    public function __construct(PdfPurchaseRequest $pdfPurchaseRequest, User $approver)
    {
        $this->pdfPurchaseRequest = $pdfPurchaseRequest;
        $this->approver = $approver;
    }

    public function handle(): void
    {
        // SỬA LẠI ĐỂ GỌI ĐÚNG MAILABLE CHUNG
        Mail::to($this->approver->email)
            ->send(new PurchaseRequestNotification($this->pdfPurchaseRequest));
    }
}
