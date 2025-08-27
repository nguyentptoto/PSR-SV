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
use App\Mail\PdfPurchaseRequestNotification;
use Illuminate\Support\Facades\Log;

class SendPdfApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pdfPurchaseRequest;
    protected $recipientId;
    protected $notificationType;

    public function __construct(PdfPurchaseRequest $pdfPurchaseRequest, User $recipient, string $notificationType = 'approval')
    {
        $this->pdfPurchaseRequest = $pdfPurchaseRequest;
        $this->recipientId = $recipient->id;
        $this->notificationType = $notificationType;
    }

    public function handle(): void
    {
        Log::info('--- SendPdfApprovalNotification Job Started ---');
        Log::info('Job is processing for PR ID:', ['pr_id' => $this->pdfPurchaseRequest->id]);

        $recipient = User::find($this->recipientId);

        Log::info('After re-fetching recipient:', [
            'recipient_id_in_job' => $this->recipientId,
            'recipient_found' => ($recipient ? 'Yes' : 'No'),
            'recipient_object_dump' => print_r($recipient, true),
        ]);

        if (!$recipient || empty($recipient->email)) {
            Log::error('Recipient user not found or missing email in Job. Throwing exception to fail job.', [
                'recipient_id' => $this->recipientId,
            ]);
            throw new \Exception("Invalid recipient user ID or missing email property.");
        }

        Mail::to($recipient->email)
            ->send(new PdfPurchaseRequestNotification(
                $this->pdfPurchaseRequest,
                $recipient,
                $this->notificationType
            ));

    }
}
