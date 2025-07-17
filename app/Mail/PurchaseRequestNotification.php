<?php

namespace App\Mail;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
// Không cần dùng Envelope và Content nếu dùng build() truyền thống
// use Illuminate\Mail\Mailables\Envelope;
// use Illuminate\Mail\Mailables\Content;

class PurchaseRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $purchaseRequest;

    public function __construct(PurchaseRequest $purchaseRequest)
    {
        $this->purchaseRequest = $purchaseRequest;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Định nghĩa chủ đề email
        $subject = 'Thông báo: Phiếu đề nghị cần xử lý (' . $this->purchaseRequest->pia_code . ')';

        // Định nghĩa nội dung email sử dụng markdown view
        return $this->subject($subject)
                    ->markdown('emails.purchase_request_notification'); // Sử dụng phương thức markdown()

        // Hoặc nếu bạn muốn dùng view HTML
        // return $this->subject($subject)
        //             ->view('emails.purchase_request_notification_html');
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
