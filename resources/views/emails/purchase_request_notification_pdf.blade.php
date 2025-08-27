@component('mail::message')
# {{ $emailSubject }}

{{ $emailMessage }}

@if($notificationType === 'approval' || $notificationType === 'rejection')
**Mã Phiếu:** {{ $pdfPurchaseRequest->pia_code }}
**Người tạo:** {{ $requesterName }}
**Trạng thái hiện tại:** {{ ucfirst(str_replace('_', ' ', $pdfPurchaseRequest->status)) }} (Cấp {{ $pdfPurchaseRequest->current_rank_level }})
@endif

@if($notificationType === 'approval')
Vui lòng nhấn vào nút bên dưới để xem chi tiết và thực hiện hành động.
@component('mail::button', ['url' => route('users.pdf-approvals.index')])
Xem chi tiết Phiếu Chờ Duyệt
@endcomponent
@elseif($notificationType === 'completion_requesting_group' || $notificationType === 'completion_all' || $notificationType === 'rejection')
Vui lòng nhấn vào nút bên dưới để xem chi tiết phiếu.
@component('mail::button', ['url' => route('users.pdf-requests.show', $pdfPurchaseRequest->id)])
Xem chi tiết Phiếu
@endcomponent
@endif

Cảm ơn,<br>
{{ config('app.name') }}
@endcomponent
