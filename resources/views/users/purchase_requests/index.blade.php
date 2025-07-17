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
    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
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
                            <td>{{ $request->id }}</td>
                            <td>{{ $request->pia_code }}</td>
                            <td>{{ $request->branch->name ?? 'N/A' }}</td>
                            <td>{{ number_format($request->total_amount, 2) }} {{ $request->currency }}</td>
                            <td>
                                <span class="badge {{ $request->status_class }}">{{ $request->status_text }}</span>
                            </td>
                            <td>{{ $request->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <a href="{{ route('users.purchase-requests.show', $request->id) }}" class="btn btn-sm btn-info" title="Xem chi tiết">
                                    <i class="bi bi-eye"></i>
                                </a>

                                 {{-- Chỉ hiển thị nút Sửa/Xóa khi phiếu đang chờ duyệt và chưa qua cấp 2 --}}
                                @if ($request->status == 'pending_approval' && $request->current_rank_level <= 2)
                                    <a href="{{ route('users.purchase-requests.edit', $request->id) }}" class="btn btn-sm btn-primary" title="Chỉnh sửa">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Xóa"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteConfirmationModal"
                                            data-delete-url="{{ route('users.purchase-requests.destroy', $request->id) }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">Bạn chưa tạo phiếu đề nghị nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $purchaseRequests->links() }}
        </div>
    </div>
</div>

{{-- ✅ THÊM MỚI: Modal xác nhận xóa --}}
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
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
@endsection

@push('scripts')
{{-- ✅ THÊM MỚI: Script để xử lý modal xóa --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteModal = document.getElementById('deleteConfirmationModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', event => {
                // Lấy nút đã được nhấn để mở modal
                const button = event.relatedTarget;
                // Lấy URL xóa từ thuộc tính data-delete-url của nút
                const deleteUrl = button.getAttribute('data-delete-url');
                // Tìm form xóa bên trong modal
                const deleteForm = deleteModal.querySelector('#delete-form');
                // Gán URL xóa cho action của form
                deleteForm.setAttribute('action', deleteUrl);
            });
        }
    });
</script>
@endpush
