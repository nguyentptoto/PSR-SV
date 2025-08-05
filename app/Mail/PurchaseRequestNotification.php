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

    public function __construct(Model $requestModel)
    {
        if ($requestModel instanceof PurchaseRequest) {
            $this->purchaseRequest = $requestModel;
            $this->requestType = 'Excel';
        } elseif ($requestModel instanceof PdfPurchaseRequest) {
            $this->pdfPurchaseRequest = $requestModel;
            $this->requestType = 'PDF';
        }
    }

    public function build()
    {
        if ($this->purchaseRequest) {
            $this->purchaseRequest->load('requester');
            $this->requesterName = $this->purchaseRequest->requester->name ?? 'N/A';
            $piaCode = $this->purchaseRequest->pia_code;
            $viewName = 'emails.purchase_request_notification'; // Tên file view cho phiếu Excel
        } elseif ($this->pdfPurchaseRequest) {
            $this->pdfPurchaseRequest->load('requester');
            $this->requesterName = $this->pdfPurchaseRequest->requester->name ?? 'N/A';
            $piaCode = $this->pdfPurchaseRequest->pia_code;
            $viewName = 'emails.purchase_request_notification_pdf'; // Tên file view cho phiếu PDF
        } else {
            $this->requesterName = 'N/A';
            $piaCode = 'N/A';
            $viewName = 'emails.purchase_request_notification_generic'; // View mặc định cho các trường hợp khác
        }

        $subject = "Thông báo: Có phiếu đề nghị mua hàng cần xử lý ({$this->requestType} - {$piaCode})";

        return $this->subject($subject)
            ->markdown($viewName) // Dùng biến $viewName để chọn view
            ->with([
                'purchaseRequest' => $this->purchaseRequest,
                'pdfPurchaseRequest' => $this->pdfPurchaseRequest,
                'requestType' => $this->requestType,
                'requesterName' => $this->requesterName,
            ]);
    }

    public function attachments()
    {
        return [];
    }
}
