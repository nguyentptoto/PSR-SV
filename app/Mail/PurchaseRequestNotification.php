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
    public $requestType;
    public $requesterName;

    public function __construct(PurchaseRequest $purchaseRequest)
{
    $this->purchaseRequest = $purchaseRequest;
    $this->requestType = 'Excel';
}

    public function build()
{
    $this->purchaseRequest->load('requester');
    $this->requesterName = $this->purchaseRequest->requester->name ?? 'N/A';
    $piaCode = $this->purchaseRequest->pia_code;
    $viewName = 'emails.purchase_request_notification';

    $subject = "Thông báo: Có phiếu đề nghị mua hàng cần xử lý ({$this->requestType} - {$piaCode})";

    return $this->subject($subject)
        ->markdown($viewName)
        ->with([
            'purchaseRequest' => $this->purchaseRequest,
            'requestType' => $this->requestType,
            'requesterName' => $this->requesterName,
        ]);
}

    public function attachments()
    {
        return [];
    }
}
