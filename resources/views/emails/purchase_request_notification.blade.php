@component('mail::message')
# Thông báo Phiếu Đề Nghị

Xin chào,

Bạn có một phiếu đề nghị mới cần được xử lý.

**Mã Phiếu:** {{ $purchaseRequest->pia_code }}
**Người tạo:** {{ $purchaseRequest->requester->name }}
**Trạng thái hiện tại:** Chờ duyệt {{ $purchaseRequest->status_text }} (Cấp {{ $purchaseRequest->current_rank_level }})

Vui lòng nhấn vào nút bên dưới để xem chi tiết và thực hiện hành động.

@component('mail::button', ['url' => route('users.purchase-requests.show', $purchaseRequest->id)])
Xem chi tiết Phiếu
@endcomponent

Cảm ơn,<br>
{{ config('app.name') }}
@endcomponent
