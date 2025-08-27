<?php

namespace App\Mail;

use App\Models\PdfPurchaseRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PdfPurchaseRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfPurchaseRequest;
    public $requesterName;
    public $recipient;
    public $notificationType;
    public $emailSubject;
    public $emailMessage;

    public function __construct(PdfPurchaseRequest $pdfPurchaseRequest, User $recipient, string $notificationType = 'approval')
    {
        $pdfPurchaseRequest->load('requester');
        $this->pdfPurchaseRequest = $pdfPurchaseRequest;
        $this->requesterName = $pdfPurchaseRequest->requester->name ?? 'N/A';
        $this->recipient = $recipient;
        $this->notificationType = $notificationType;

        $this->setNotificationContent();
    }

    public function build()
    {
        return $this->subject($this->emailSubject)
                    ->markdown('emails.purchase_request_notification_pdf')
                    ->with([
                        'pdfPurchaseRequest' => $this->pdfPurchaseRequest,
                        'requesterName' => $this->requesterName,
                        'recipientName' => $this->recipient->name,
                        'notificationType' => $this->notificationType,
                        'emailMessage' => $this->emailMessage,
                    ]);
    }

    protected function setNotificationContent()
    {
        $piaCode = $this->pdfPurchaseRequest->pia_code;
        $requesterName = $this->requesterName;
        $recipientName = $this->recipient->name;

        switch ($this->notificationType) {
            case 'approval':
                $this->emailSubject = "Thông báo: Có phiếu đề nghị mua hàng cần xử lý (PDF - {$piaCode})";
                $this->emailMessage = "Xin chào {$recipientName},\n\nBạn có một phiếu đề nghị mới cần được xử lý.";
                break;
            case 'completion_requesting_group':
                $this->emailSubject = "Thông báo: Phiếu đề nghị PDF {$piaCode} đã hoàn thành Phòng Đề Nghị";
                $this->emailMessage = "Xin chào {$recipientName},\n\nPhiếu đề nghị PDF **{$piaCode}** của bạn đã hoàn thành quá trình phê duyệt tại **Phòng Đề Nghị** và đang được chuyển tiếp sang **Phòng Mua**.";
                break;
            case 'completion_all':
                $this->emailSubject = "Thông báo: Phiếu đề nghị PDF {$piaCode} đã hoàn thành toàn bộ quy trình";
                $this->emailMessage = "Xin chào {$recipientName},\n\nPhiếu đề nghị PDF **{$piaCode}** của bạn đã **hoàn thành toàn bộ quy trình phê duyệt**.";
                break;
            case 'rejection':
                $this->emailSubject = "Thông báo: Phiếu đề nghị PDF {$piaCode} đã bị từ chối";
                $rejectionReason = $this->pdfPurchaseRequest->rejection_reason ?? 'Không có lý do cụ thể.';
                $this->emailMessage = "Xin chào {$recipientName},\n\nPhiếu đề nghị PDF **{$piaCode}** đã bị **từ chối** bởi người duyệt.\n\n**Lý do từ chối:** {$rejectionReason}\n\nVui lòng kiểm tra phiếu để biết thêm chi tiết.";
                break;
            default:
                $this->emailSubject = "Thông báo: Cập nhật phiếu đề nghị PDF (Mã: {$piaCode})";
                $this->emailMessage = "Xin chào {$recipientName},\n\nCó cập nhật về phiếu đề nghị của bạn.";
                break;
        }
    }
}
