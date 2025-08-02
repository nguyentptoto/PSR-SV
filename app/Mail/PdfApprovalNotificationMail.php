<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
// Removed: use Illuminate\Mail\Mailables\Content;
// Removed: use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\PdfPurchaseRequest; // Import PdfPurchaseRequest model
use App\Models\User; // Import User model

class PdfApprovalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfPurchaseRequest; // Đối tượng PdfPurchaseRequest
    public $approver;

    /**
     * Create a new message instance.
     *
     * @param \App\Models\PdfPurchaseRequest $pdfPurchaseRequest
     * @param \App\Models\User $approver
     * @return void
     */
    public function __construct(PdfPurchaseRequest $pdfPurchaseRequest, User $approver)
    {
        $this->pdfPurchaseRequest = $pdfPurchaseRequest;
        $this->approver = $approver;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.approval_notification') // Có thể dùng chung view hoặc tạo view riêng
                    ->subject('Yêu cầu duyệt phiếu PDF: ' . $this->pdfPurchaseRequest->pia_code) // Lấy pia_code từ PdfPurchaseRequest
                    ->with([
                        'request' => $this->pdfPurchaseRequest,
                        'approver' => $this->approver,
                        'requestType' => 'PdfPurchaseRequest', // Xác định loại request
                    ]);
    }

    // Removed envelope() and content() methods for PHP 7.4 compatibility
}
