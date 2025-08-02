<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PdfPurchaseRequest; // Import model PdfPurchaseRequest
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use App\Mail\PdfApprovalNotificationMail; // Import PdfApprovalNotificationMail

class SendPdfApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pdfPurchaseRequest; // Đối tượng PdfPurchaseRequest
    protected $approvers; // Collection của các User là người duyệt

    /**
     * Create a new job instance.
     *
     * @param \App\Models\PdfPurchaseRequest $pdfPurchaseRequest
     * @param \Illuminate\Support\Collection $approvers
     * @return void
     */
    public function __construct(PdfPurchaseRequest $pdfPurchaseRequest, Collection $approvers)
    {
        $this->pdfPurchaseRequest = $pdfPurchaseRequest;
        $this->approvers = $approvers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Gửi email thông báo cho từng người duyệt
        foreach ($this->approvers as $approver) {
            Mail::to($approver->email)->send(new PdfApprovalNotificationMail($this->pdfPurchaseRequest, $approver));
        }
    }
}
