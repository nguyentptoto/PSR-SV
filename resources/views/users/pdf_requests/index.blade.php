@extends('admin')
@section('title', 'Danh Sách Phiếu Đề Nghị PDF')

@section('content')
    <h2 class="mb-4">Danh Sách Phiếu Đề Nghị (PDF)</h2>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-check-circle"></i> Thành công!</h5>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-ban"></i> Lỗi!</h5>
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Phiếu của tôi (PDF)</h3>
            <div class="card-tools">
                <a href="{{ route('users.pdf-requests.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Tạo Phiếu PDF mới
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 10px;">#</th>
                            <th>Mã Phiếu (PR NO)</th>
                            <th>Người yêu cầu</th>
                            <th>Trạng thái</th>
                            <th>Cấp duyệt hiện tại</th>
                            <th>Ngày tạo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pdfPurchaseRequests as $pdfPr)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $pdfPr->pia_code }}</td>
                                <td>{{ $pdfPr->requester->name ?? 'N/A' }}</td>
                                <td>
    @php
        $statusClass = '';
        $statusText = '';
        switch ($pdfPr->status) {
            case 'pending_approval':
                $statusClass = 'badge badge-warning';
                $statusText = 'Đang chờ duyệt';
                break;
            case 'approved':
                $statusClass = 'badge badge-success';
                $statusText = 'Đã phê duyệt';
                break;
            case 'rejected':
                $statusClass = 'badge badge-danger';
                $statusText = 'Đã từ chối';
                break;
            case 'completed':
                $statusClass = 'badge badge-info';
                $statusText = 'Đã hoàn thành';
                break;
            default:
                $statusClass = 'badge badge-secondary';
                $statusText = $pdfPr->status; // Giữ nguyên nếu không có trong danh sách
                break;
        }
    @endphp
    <span class="{{ $statusClass }}">{{ $statusText }}</span>
</td>
                                <td>Cấp {{ $pdfPr->current_rank_level }}</td>
                                <td>{{ $pdfPr->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    {{-- Nút xem chi tiết (nếu có show method cho PDF PR) --}}
                                    <a href="{{ route('users.pdf-requests.show', $pdfPr->id) }}" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Xem
                                    </a>

                                    {{-- Nút ký lại (nếu trạng thái cho phép) --}}
                                    @if ($pdfPr->status === 'pending_approval' && $pdfPr->current_rank_level === 1 && $pdfPr->requester_id === Auth::id())
                                        <a href="{{ route('users.pdf-requests.preview-sign', $pdfPr->id) }}" class="btn btn-warning btn-sm">
                                            <i class="fas fa-signature"></i> Ký lại
                                        </a>
                                    @elseif ($pdfPr->status === 'pending_approval' && $pdfPr->signed_pdf_path)
                                        {{-- SỬ DỤNG asset() CHO FILE ĐÃ KÝ --}}
                                        <a href="{{ asset('storage/' . $pdfPr->signed_pdf_path) }}" target="_blank" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-file-pdf"></i> Xem PDF đã ký
                                        </a>
                                    @elseif ($pdfPr->original_pdf_path)
                                        {{-- SỬ DỤNG route() CHO FILE GỐC (vì nó được phục vụ qua Controller) --}}
                                        <a href="{{ route('users.pdf-requests.view-file', $pdfPr->id) }}" target="_blank" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-file-pdf"></i> Xem PDF gốc
                                        </a>
                                    @endif

                                    {{-- Các nút hành động edit và delete cho PDF PR --}}
                                    @if ($pdfPr->status === 'pending_approval' && $pdfPr->current_rank_level === 1 && $pdfPr->requester_id === Auth::id())
                                        <a href="{{ route('users.pdf-requests.edit', $pdfPr->id) }}" class="btn btn-primary btn-sm" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" title="Xóa"
                                            data-bs-toggle="modal" data-bs-target="#deletePdfConfirmationModal"
                                            data-delete-url="{{ route('users.pdf-requests.destroy', $pdfPr->id) }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">Không có phiếu đề nghị PDF nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer clearfix">
            {{ $pdfPurchaseRequests->links() }}
        </div>
    </div>

    {{-- Modal xác nhận xóa cho PDF PR --}}
    <div class="modal fade" id="deletePdfConfirmationModal" tabindex="-1" aria-labelledby="deletePdfConfirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePdfConfirmationModalLabel">Xác nhận Xóa Phiếu PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Bạn có chắc chắn muốn xóa phiếu đề nghị PDF này không? Hành động này không thể hoàn tác và sẽ xóa cả file PDF liên quan.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <form id="delete-pdf-form" method="POST" action="">
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deletePdfModal = document.getElementById('deletePdfConfirmationModal');
            if (deletePdfModal) {
                deletePdfModal.addEventListener('show.bs.modal', event => {
                    const button = event.relatedTarget;
                    const deleteUrl = button.getAttribute('data-delete-url');
                    const deleteForm = deletePdfModal.querySelector('#delete-pdf-form');
                    deleteForm.setAttribute('action', deleteUrl);
                });
            }
        });
    </script>
@endpush
