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

    // Giữ lại một thuộc tính duy nhất để truyền model vào
    public Model $requestModel;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Model $requestModel)
    {
        // CHỈ LƯU MODEL, KHÔNG XỬ LÝ GÌ Ở ĐÂY
        $this->requestModel = $requestModel;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // BẮT BUỘC: Tải relationship tại đây, trước khi sử dụng
        $this->requestModel->load('requester');

        $purchaseRequest = null;
        $pdfPurchaseRequest = null;
        $requesterName = $this->requestModel->requester->name ?? 'N/A';
        $requestType = '';
        $piaCode = $this->requestModel->pia_code;

        // Xử lý logic và gán thuộc tính ở đây
        if ($this->requestModel instanceof PurchaseRequest) {
            $purchaseRequest = $this->requestModel;
            $requestType = 'Excel';
        } elseif ($this->requestModel instanceof PdfPurchaseRequest) {
            $pdfPurchaseRequest = $this->requestModel;
            $requestType = 'PDF';
        }

        $subject = "Thông báo: Có phiếu đề nghị mua hàng cần xử lý ({$requestType} - {$piaCode})";

        return $this->subject($subject)
            ->markdown('emails.purchase_request_notification')
            ->with([
                'requesterName' => $requesterName,
                'requestType' => $requestType,
                'purchaseRequest' => $purchaseRequest, // có thể null
                'pdfPurchaseRequest' => $pdfPurchaseRequest, // có thể null
            ]);
    }
}
