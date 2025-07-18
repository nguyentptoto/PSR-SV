@extends('admin')
@section('title', 'Danh sách Phiếu Đề Nghị')

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Danh sách Phiếu Đề Nghị của bạn</h5>
                <a href="{{ route('users.purchase-requests.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Tạo Phiếu mới
                </a>
            </div>
        </div>
        {{-- ✅ SỬA LỖI: Bọc toàn bộ card-body trong một form --}}
        <form action="{{ route('users.purchase-requests.bulk-export-pdf') }}" method="POST" id="bulk-action-form">
            @csrf
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="mb-3">
                    {{-- ✅ SỬA ĐỔI: Chuyển thành type="button" để script xử lý --}}
                    <button type="button" class="btn btn-danger" id="bulk-export-pdf-btn">
                        <i class="bi bi-file-earmark-pdf"></i> In các mục đã chọn ra PDF
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10px;"><input type="checkbox" id="check-all"></th>
                                <th>ID</th>
                                <th>Mã Phiếu (PR NO)</th>
                                <th>Chi nhánh</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th style="width: 150px;">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseRequests as $request)
                                <tr>
                                    {{-- ✅ SỬA ĐỔI: Thêm data-exportable để JS kiểm tra --}}
                                    <td>
                                        <input type="checkbox" name="request_ids[]" class="request-checkbox"
                                            value="{{ $request->id }}"
                                            data-exportable="{{ in_array($request->status, ['purchasing_approval', 'completed']) ? 'true' : 'false' }}">
                                    </td>
                                    <td>{{ $request->id }}</td>
                                    <td>{{ $request->pia_code }}</td>
                                    <td>{{ $request->branch->name ?? 'N/A' }}</td>
                                    <td>{{ number_format($request->total_amount, 2) }} {{ $request->currency }}</td>
                                    <td>
                                        <span
                                            class="badge {{ $request->status_class }}">{{ $request->status_text }}</span>
                                    </td>
                                    <td>{{ $request->created_at->format('d/m/Y H:i:s') }}</td>
                                    <td>
                                        <a href="{{ route('users.purchase-requests.show', $request->id) }}"
                                            class="btn btn-sm btn-info" title="Xem chi tiết">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($request->status == 'pending_approval' && $request->current_rank_level <= 2)
                                            <a href="{{ route('users.purchase-requests.edit', $request->id) }}"
                                                class="btn btn-sm btn-primary" title="Chỉnh sửa">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Xóa"
                                                data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                                data-delete-url="{{ route('users.purchase-requests.destroy', $request->id) }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">Bạn chưa tạo phiếu đề nghị nào.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $purchaseRequests->links() }}
                </div>
            </div>
        </form>
    </div>

    {{-- ✅ THÊM MỚI: Modal xác nhận xóa --}}
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Xác nhận Xóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Bạn có chắc chắn muốn xóa phiếu đề nghị này không? Hành động này không thể hoàn tác.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form id="delete-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Xác nhận Xóa</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ✅ THÊM MỚI: Modal Cảnh báo In hàng loạt --}}
    <div class="custom-modal-overlay" id="warningModalOverlay">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title" id="warningModalTitle">Xác nhận In</h5>
                <button type="button" class="custom-modal-close" data-modal-dismiss>&times;</button>
            </div>
            <div class="custom-modal-body" id="warningModalBody">
                {{-- Nội dung sẽ được điền bằng JavaScript --}}
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-dismiss>Hủy</button>
                <button type="button" class="btn btn-danger" id="confirm-export-btn">Tiếp tục</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- ✅ THÊM MỚI: Script để xử lý modal xóa --}}
    <script>
document.addEventListener('DOMContentLoaded', function () {
        const deleteModal = document.getElementById('deleteConfirmationModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const deleteUrl = button.getAttribute('data-delete-url');
                const deleteForm = deleteModal.querySelector('#delete-form');
                deleteForm.setAttribute('action', deleteUrl);
            });
        }

        const checkAll = document.getElementById('check-all');
        const checkboxes = document.querySelectorAll('.request-checkbox');
        const bulkForm = document.getElementById('bulk-action-form');
        const bulkExportBtn = document.getElementById('bulk-export-pdf-btn');

        const warningModalOverlay = document.getElementById('warningModalOverlay');
        const warningModalBody = document.getElementById('warningModalBody');
        const confirmExportBtn = document.getElementById('confirm-export-btn');
        const dismissButtons = document.querySelectorAll('[data-modal-dismiss]');

        if (checkAll) {
            checkAll.addEventListener('click', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        function closeModal() {
            warningModalOverlay.classList.remove('show');
            setTimeout(() => { warningModalOverlay.style.display = 'none'; }, 300);
        }

        dismissButtons.forEach(btn => btn.addEventListener('click', closeModal));

        if (bulkForm && bulkExportBtn) {
            bulkExportBtn.addEventListener('click', function() {
                const checkedCheckboxes = document.querySelectorAll('.request-checkbox:checked');
                const exportableCheckboxes = document.querySelectorAll('.request-checkbox:checked[data-exportable="true"]');
                const ineligibleCount = checkedCheckboxes.length - exportableCheckboxes.length;

                if (checkedCheckboxes.length === 0) {
                    alert('Vui lòng chọn ít nhất một phiếu để thực hiện hành động.');
                    return;
                }

                if (exportableCheckboxes.length === 0) {
                    alert('Không có phiếu nào trong số các mục đã chọn đủ điều kiện để in.');
                    return;
                }

                if (ineligibleCount > 0) {
                    warningModalBody.innerHTML = `Bạn đã chọn tổng cộng ${checkedCheckboxes.length} phiếu. <br>Trong đó có <strong>${ineligibleCount} phiếu chưa đủ điều kiện</strong> để in. <br><br>Chỉ <strong>${exportableCheckboxes.length} phiếu hợp lệ</strong> sẽ được xử lý. Bạn có muốn tiếp tục?`;
                } else {
                    warningModalBody.innerHTML = `Bạn có chắc chắn muốn in ${exportableCheckboxes.length} phiếu đã chọn?`;
                }

                warningModalOverlay.style.display = 'flex';
                setTimeout(() => warningModalOverlay.classList.add('show'), 10);
            });
        }

        if (confirmExportBtn) {
            confirmExportBtn.addEventListener('click', function() {
                const exportableCheckboxes = document.querySelectorAll('.request-checkbox:checked[data-exportable="true"]');
                const oldInputs = bulkForm.querySelectorAll('input[name="request_ids[]"]');
                oldInputs.forEach(input => input.remove());

                exportableCheckboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'request_ids[]';
                    hiddenInput.value = checkbox.value;
                    bulkForm.appendChild(hiddenInput);
                });

                bulkForm.submit();
                closeModal();
            });
        }
    });
    </script>
@endpush
