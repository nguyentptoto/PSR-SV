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
        // 1. TIÊU ĐỀ CHUNG - Không chứa thông tin động
        // Tiêu đề này sẽ giống hệt nhau cho mọi email.
        $subject = 'Thông báo: Có phiếu đề nghị mua hàng cần xử lý';

        // 2. ID THAM CHIẾU CHUNG - Một chuỗi cố định
        // ID này cũng giống hệt nhau cho mọi email.
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $threadId = "<purchase-request-notifications@{$domain}>";

        return $this->subject($subject)
            ->markdown('emails.purchase_request_notification')
            ->withSwiftMessage(function ($message) use ($threadId) {
                // 3. THIẾT LẬP HEADER
                // Vì subject và threadId luôn giống nhau, các hòm thư sẽ gom tất cả vào một chỗ.
                $message->getHeaders()->addTextHeader('References', $threadId);
            });
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
