@component('mail::message')
# Thông báo Phiếu Đề Nghị

Xin chào,

Bạn có một phiếu đề nghị mới cần được xử lý.

{{-- Kiểm tra xem có phải phiếu Excel hay không --}}
@if(isset($purchaseRequest) && $purchaseRequest)
    **Loại phiếu:** Excel
    **Mã Phiếu:** {{ $purchaseRequest->pia_code }}
    **Người tạo:** {{ $purchaseRequest->requester->name }}
    **Trạng thái hiện tại:** Chờ duyệt {{ $purchaseRequest->status_text }} (Cấp {{ $purchaseRequest->current_rank_level }})

    Vui lòng nhấn vào nút bên dưới để xem chi tiết và thực hiện hành động.

    @component('mail::button', ['url' => route('users.purchase-requests.show', $purchaseRequest->id)])
        Xem chi tiết Phiếu
    @endcomponent

{{-- Kiểm tra xem có phải phiếu PDF hay không --}}
@elseif(isset($pdfPurchaseRequest) && $pdfPurchaseRequest)
    **Loại phiếu:** PDF
    **Mã Phiếu:** {{ $pdfPurchaseRequest->pia_code }}
    **Người tạo:** {{ $pdfPurchaseRequest->requester->name }}
    **Trạng thái hiện tại:** Chờ duyệt (Cấp {{ $pdfPurchaseRequest->current_rank_level }})

    Vui lòng nhấn vào nút bên dưới để xem chi tiết và thực hiện hành động.

    @component('mail::button', ['url' => route('users.pdf-requests.show', $pdfPurchaseRequest->id)])
        Xem chi tiết Phiếu
    @endcomponent
@endif {{-- ✅ SỬA LỖI: Thêm @endif để đóng khối lệnh --}}

Cảm ơn,<br>
{{ config('app.name') }}
@endcomponent
