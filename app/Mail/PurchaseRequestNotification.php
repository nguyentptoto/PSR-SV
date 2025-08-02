<?php

namespace App\Mail;

use App\Models\PurchaseRequest;
use App\Models\PdfPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $purchaseRequest;
    public $pdfPurchaseRequest;
    public $requesterName;
    public $requestType;

    public function __construct(Model $requestModel)
    {
        if ($requestModel instanceof PurchaseRequest) {
            $this->purchaseRequest = $requestModel;
            $this->requesterName = $requestModel->requester->name ?? 'N/A';
            $this->requestType = 'Excel';
        } elseif ($requestModel instanceof PdfPurchaseRequest) {
            $this->pdfPurchaseRequest = $requestModel;
            $this->requesterName = $requestModel->requester->name ?? 'N/A';
            $this->requestType = 'PDF';
        }
    }

    public function build()
    {
        $subject = 'Thông báo: Có phiếu đề nghị mua hàng cần xử lý (' . $this->requestType . ' - ' . ($this->purchaseRequest->pia_code ?? $this->pdfPurchaseRequest->pia_code) . ')';

        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $threadId = "<purchase-request-notifications@{$domain}>";

        return $this->subject($subject)
            ->markdown('emails.purchase_request_notification')
            ->with([
                'requesterName' => $this->requesterName,
                'requestType' => $this->requestType,
                'purchaseRequest' => $this->purchaseRequest,
                'pdfPurchaseRequest' => $this->pdfPurchaseRequest,
            ])
            ->withSwiftMessage(function ($message) use ($threadId) {
                $message->getHeaders()->addTextHeader('References', $threadId);
            });
    }

    public function attachments()
    {
        return [];
    }
}
