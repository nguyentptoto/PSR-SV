@extends('admin')
@section('title', 'Chi tiết Phiếu: ' . $purchaseRequest->pia_code)

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Chi tiết Phiếu Đề Nghị #{{ $purchaseRequest->pia_code }}</h5>
                <div>

                    @if (Auth::id() === $purchaseRequest->requester_id &&
                            $purchaseRequest->status === 'pending_approval' &&
                            $purchaseRequest->current_rank_level <= 2)
                        <a href="{{ route('users.purchase-requests.edit', $purchaseRequest->id) }}" class="btn btn-primary"><i
                                class="bi bi-pencil-square"></i> Chỉnh sửa</a>
                    @endif

                    {{-- Nút "Quay lại" sẽ tự động trỏ về đúng trang trước đó --}}
                    @php
                        $backUrl =
                            ($from ?? 'my-requests') === 'approvals'
                                ? route('users.approvals.index')
                                : route('users.purchase-requests.index');
                    @endphp

                    @if (in_array($purchaseRequest->status, ['purchasing_approval', 'completed']))
                        <a href="{{ route('users.purchase-requests.export', $purchaseRequest->id) }}"
                            class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                        <a href="{{ route('users.purchase-requests.export.pdf', $purchaseRequest) }}"
                            class="btn btn-danger">
                            Export to PDF
                        </a>
                    @endif

                    @php
                        $backUrl =
                            ($from ?? 'my-requests') === 'approvals'
                                ? route('users.approvals.index')
                                : route('users.purchase-requests.index');
                    @endphp
                    <a href="{{ $backUrl }}" class="btn btn-secondary">Quay lại</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            {{-- Phần thông tin chung --}}
            <div class="row mb-4">
            <div class="col-md-6">
                <p><strong>PR NO:</strong> {{ $purchaseRequest->pia_code }}</p>
                <p><strong>Người tạo:</strong> {{ $purchaseRequest->requester->name ?? 'N/A' }}</p>
                <p><strong>Phòng ban của người tạo:</strong> {{ $purchaseRequest->section->name ?? 'N/A' }}</p>
                <p><strong>Phòng ban yêu cầu:</strong> {{ $purchaseRequest->executingDepartment->name ?? 'N/A' }} ({{ $purchaseRequest->executingDepartment->code ?? '' }})</p>
            </div>
            <div class="col-md-6">
                <p><strong>Nhà máy (Plant):</strong> {{ $purchaseRequest->branch->name ?? 'N/A' }}</p>
                <p><strong>Ngày yêu cầu giao hàng:</strong> {{ $purchaseRequest->requested_delivery_date ? $purchaseRequest->requested_delivery_date->format('d/m/Y') : '' }}</p>
                <p><strong>Trạng thái:</strong> <span class="badge {{ $purchaseRequest->status_class }}">{{ $purchaseRequest->status_text }}</span></p>

                {{-- ✅ ĐÃ CẬP NHẬT: Hiển thị Mức độ ưu tiên và Ghi chú --}}
                <p><strong>Mức độ ưu tiên:</strong>
                    @switch($purchaseRequest->priority)
                        @case('urgent')
                            <span class="badge bg-danger">Urgent/Khẩn cấp</span>
                            @break
                        @case('quotation_only')
                            <span class="badge bg-info">Quotation only/Báo giá</span>
                            @break
                        @case('normal')
                            <span class="badge bg-success">Normal/Bình thường</span>
                            @break
                        @case(null)
                            <span class="badge bg-secondary">Chưa xác định</span>
                        @break
                        @default
                            <span class="badge bg-secondary">Chưa xác định</span>
                            @break
                    @endswitch
                </p>
                <p><strong>Tổng tiền:</strong> <span class="fw-bold">{{ number_format($purchaseRequest->total_amount, 2) }} {{ $purchaseRequest->currency }}</span></p>
            </div>
               @if($purchaseRequest->attachment_path)
            <div class="col-12 mt-2">
                <p><strong>File đính kèm:</strong>
                    <a href="{{ Storage::url($purchaseRequest->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download"></i> Tải về / Xem file
                    </a>
                </p>
            </div>
            @endif
            @if($purchaseRequest->remarks)
            <div class="col-12 mt-2">
                <p><strong>Ghi chú (Remarks):</strong></p>
                <p class="border p-2 rounded bg-light">{{ $purchaseRequest->remarks }}</p>
            </div>
            @endif
        </div>

            {{-- Phần chi tiết hàng hóa --}}
            <h5 class="mt-4">Các mặt hàng trong phiếu</h5>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered table-striped">
                    <thead class="table-light text-center">
                        <tr>
                            <th>#</th>
                            <th>Mã hàng</th>
                            <th>Tên hàng</th>
                            <th>SL Đặt</th>
                            <th>Đơn vị</th>
                            <th>Giá dự tính</th>
                            <th>Thành tiền</th>
                            <th>Mã phòng SD</th>
                            <th>Plant hệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseRequest->items as $index => $item)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>{{ $item->item_code }}</td>
                                <td>{{ $item->item_name }}</td>
                                <td class="text-center">{{ $item->order_quantity }}</td>
                                <td class="text-center">{{ $item->order_unit }}</td>
                                <td class="text-end">{{ number_format($item->estimated_price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->subtotal, 2) }}</td>
                                <td>{{ $item->using_dept_code }}</td>
                                <td>{{ $item->plant_system }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">Phiếu này không có mặt hàng nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Card Lịch sử Phiếu --}}
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i>
                Lịch sử Phiếu
            </h3>
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                @forelse($purchaseRequest->approvalHistories as $history)
                    <li class="list-group-item">
                        <div class="d-flex">
                            <div class="me-3">
<<<<<<< HEAD
                               @if ($history->signature_image_path && $history->signature_image_path !== 'no-signature.png')
=======
                                   @if ($history->signature_image_path && $history->signature_image_path !== 'no-signature.png')
>>>>>>> 008a4b41ca5eda2e1bb01a13d8f90c7b4f76a3ab
    {{-- Nếu có chữ ký hợp lệ, hiển thị nó từ storage --}}
    <img src="{{ asset('storage/' . $history->signature_image_path) }}" alt="Chữ ký"
         class="img-thumbnail" style="width: 80px; height: 80px; object-fit: contain;">
@else
                                    <div class="img-thumbnail d-flex align-items-center justify-content-center"
                                        style="width: 80px; height: 80px; background-color: #f8f9fa;">
                                        <small class="text-muted">No Sig.</small>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        @if ($history->action === 'created')
                                            <span class="badge bg-success">Tạo mới</span>
                                        @elseif($history->action === 'updated')
                                            <span class="badge bg-info">Cập nhật</span>
                                        @elseif($history->action === 'approved')
                                            <span class="badge bg-primary">Đã duyệt</span>
                                        @elseif($history->action === 'rejected')
                                            <span class="badge bg-danger">Đã từ chối</span>
                                        @endif
                                        bởi <strong>{{ $history->user->name ?? 'Hệ thống' }}</strong>
                                        <small class="text-muted">({{ $history->rank_at_approval }})</small>
                                    </h6>
                                    <small>{{ $history->created_at->diffForHumans() }}</small>
                                </div>
                                @if ($history->comment)
                                    <p class="mb-1 fst-italic">"{{ $history->comment }}"</p>
                                @endif
                                <small class="text-muted">{{ $history->created_at->format('d/m/Y H:i:s') }}</small>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item">Chưa có lịch sử.</li>
                @endforelse
            </ul>
        </div>
    </div>
    {{-- ✅ THÊM MỚI: Card Hành động Phê duyệt --}}
    @can('approve', $purchaseRequest)
<div class="card card-success">
    <div class="card-header">
        <h3 class="card-title">Hành động Phê duyệt</h3>
    </div>
    <div class="card-body text-center">
        <p>Bạn có quyền xử lý phiếu này ở cấp duyệt hiện tại.</p>
        {{-- ✅ SỬA ĐỔI: Các nút này giờ sẽ kích hoạt modal tùy chỉnh --}}
        <button type="button" class="btn btn-lg btn-success approve-btn" data-action-url="{{ route('users.approvals.approve', $purchaseRequest->id) }}">
            <i class="bi bi-check-circle-fill"></i> Phê duyệt
        </button>
        <button type="button" class="btn btn-lg btn-danger reject-btn" data-action-url="{{ route('users.approvals.reject', $purchaseRequest->id) }}">
            <i class="bi bi-x-circle-fill"></i> Từ chối
        </button>
    </div>
</div>
@endcan
{{-- Modal Phê duyệt (Phiên bản tùy chỉnh) --}}
<div class="custom-modal-overlay" id="approveModalOverlay">
    <div class="custom-modal-content">
      <div class="custom-modal-header">
        <h5 class="custom-modal-title">Xác nhận Phê duyệt</h5>
        <button type="button" class="custom-modal-close" data-modal-dismiss>&times;</button>
      </div>
      <form id="approve-form" method="POST" action="">
          @csrf
          <div class="custom-modal-body">
              <p>Bạn có chắc chắn muốn phê duyệt phiếu này?</p>
              <div class="mb-3">
                  <label for="approve_comment" class="form-label">Ghi chú (tùy chọn):</label>
                  <textarea class="form-control" id="approve_comment" name="comment" rows="3"></textarea>
              </div>
          </div>
          <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-dismiss>Hủy</button>
            <button type="submit" class="btn btn-success">Xác nhận Duyệt</button>
          </div>
      </form>
    </div>
</div>

{{-- Modal Từ chối (Phiên bản tùy chỉnh) --}}
<div class="custom-modal-overlay" id="rejectModalOverlay">
    <div class="custom-modal-content">
      <div class="custom-modal-header">
        <h5 class="custom-modal-title">Lý do từ chối</h5>
        <button type="button" class="custom-modal-close" data-modal-dismiss>&times;</button>
      </div>
      <form id="reject-form" method="POST" action="">
          @csrf
          <div class="custom-modal-body">
              <div class="mb-3">
                  <label for="reject_comment" class="form-label">Vui lòng nhập lý do từ chối (bắt buộc):</label>
                  <textarea class="form-control" id="reject_comment" name="comment" rows="3" required></textarea>
              </div>
          </div>
          <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-dismiss>Hủy</button>
            <button type="submit" class="btn btn-danger">Xác nhận Từ chối</button>
          </div>
      </form>
    </div>
</div>

@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        // --- Logic cho Modal Tùy chỉnh ---
        function openModal(modalOverlay) {
            modalOverlay.css('display', 'flex');
            setTimeout(() => modalOverlay.addClass('show'), 10);
        }

        function closeModal(modalOverlay) {
            modalOverlay.removeClass('show');
            setTimeout(() => modalOverlay.css('display', 'none'), 300);
        }

        // Xử lý Modal Phê duyệt
        const approveModal = $('#approveModalOverlay');
        $('.approve-btn').on('click', function() {
            let actionUrl = $(this).data('action-url');
            approveModal.find('#approve-form').attr('action', actionUrl);
            openModal(approveModal);
        });

        // Xử lý Modal Từ chối
        const rejectModal = $('#rejectModalOverlay');
        $('.reject-btn').on('click', function() {
            let actionUrl = $(this).data('action-url');
            rejectModal.find('#reject-form').attr('action', actionUrl);
            openModal(rejectModal);
        });

        // Gắn sự kiện đóng cho tất cả các nút có thuộc tính data-modal-dismiss
        $('[data-modal-dismiss]').on('click', function() {
            closeModal($(this).closest('.custom-modal-overlay'));
        });
    });
</script>
@endpush
