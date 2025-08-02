@extends('admin')
@section('title', 'Chi Tiết Phiếu Đề Nghị PDF')

@section('content')
    <h2 class="mb-4">Chi Tiết Phiếu Đề Nghị (PDF): {{ $pdfPurchaseRequest->pia_code }}</h2>

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

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Thông tin chung</h3>
            <div class="card-tools">
                @php
                    $pdfApprovalController = app(\App\Http\Controllers\Customer\PdfApprovalController::class);
                    $canApprovePdf = $pdfApprovalController->userCanApprovePdf(Auth::user(), $pdfPurchaseRequest);
                @endphp

                @if ($pdfPurchaseRequest->status === 'pending_approval' && $pdfPurchaseRequest->current_rank_level === 1 && $pdfPurchaseRequest->requester_id === Auth::id())
                    <a href="{{ route('users.pdf-requests.edit', $pdfPurchaseRequest->id) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Chỉnh sửa
                    </a>
                    <button type="button" class="btn btn-danger btn-sm"
                        data-bs-toggle="modal" data-bs-target="#deletePdfConfirmationModal"
                        data-delete-url="{{ route('users.pdf-requests.destroy', $pdfPurchaseRequest->id) }}">
                        <i class="fas fa-trash"></i> Xóa
                    </button>
                @endif

                @if ($canApprovePdf)
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approvePdfModal">
                        <i class="fas fa-check"></i> Duyệt
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectPdfModal">
                        <i class="fas fa-times"></i> Từ chối
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Mã Phiếu (PR NO):</strong> {{ $pdfPurchaseRequest->pia_code }}</p>
                    <p><strong>Người yêu cầu:</strong> {{ $pdfPurchaseRequest->requester->name ?? 'N/A' }}</p>
                    <p><strong>Phòng ban người yêu cầu:</strong> {{ $pdfPurchaseRequest->requester->sections->first()->name ?? 'N/A' }}</p>
                    <p><strong>Nhà máy (Plant):</strong> {{ $pdfPurchaseRequest->requester->mainBranch->name ?? 'N/A' }}</p>
                    <p><strong>Trạng thái:</strong>
                        @php
                            $statusClass = '';
                            switch ($pdfPurchaseRequest->status) {
                                case 'pending_approval': $statusClass = 'badge badge-warning'; break;
                                case 'approved': $statusClass = 'badge badge-success'; break;
                                case 'rejected': $statusClass = 'badge badge-danger'; break;
                                case 'completed': $statusClass = 'badge badge-info'; break;
                                default: $statusClass = 'badge badge-secondary'; break;
                            }
                        @endphp
                        <span class="{{ $statusClass }}">{{ __($pdfPurchaseRequest->status) }}</span>
                    </p>
                    <p><strong>Cấp duyệt hiện tại:</strong> Cấp {{ $pdfPurchaseRequest->current_rank_level }}</p>
                    <p><strong>Ngày tạo:</strong> {{ $pdfPurchaseRequest->created_at->format('d/m/Y H:i:s') }}</p>
                    <p><strong>Ghi chú:</strong> {{ $pdfPurchaseRequest->remarks ?? 'Không có' }}</p>
                </div>
                <div class="col-md-6">
                    <h5>File PDF</h5>
                    <p><strong>File PDF gốc:</strong>
                        @if ($pdfPurchaseRequest->original_pdf_path)
                            <a href="{{ route('users.pdf-requests.view-file', $pdfPurchaseRequest->id) }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-file-pdf"></i> Xem PDF gốc
                            </a>
                        @else
                            Không có
                        @endif
                    </p>
                    <p><strong>File PDF đã ký:</strong>
                        @if ($pdfPurchaseRequest->signed_pdf_path)
                            <a href="{{ asset('storage/' . $pdfPurchaseRequest->signed_pdf_path) }}" target="_blank" class="btn btn-sm btn-success">
                                <i class="fas fa-file-pdf"></i> Xem PDF đã ký
                            </a>
                        @else
                            Chưa có
                        @endif
                    </p>
                    <p><strong>Vị trí ký:</strong> Trang {{ $pdfPurchaseRequest->signature_page ?? 'N/A' }}, X: {{ $pdfPurchaseRequest->signature_pos_x ?? 'N/A' }}mm, Y: {{ $pdfPurchaseRequest->signature_pos_y ?? 'N/A' }}mm</p>
                    <p><strong>Kích thước ảnh ký:</strong> Rộng: {{ $pdfPurchaseRequest->signature_width ?? 'N/A' }}mm, Cao: {{ $pdfPurchaseRequest->signature_height ?? 'N/A' }}mm</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title">Lịch sử duyệt</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Người duyệt</th>
                            <th>Cấp duyệt</th>
                            <th>Hành động</th>
                            <th>Thời gian</th>
                            <th>Chữ ký</th>
                            <th>Bình luận</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pdfPurchaseRequest->approvalHistories as $history)
                            <tr>
                                <td>{{ $history->user->name ?? 'N/A' }}</td>
                                <td>{{ $history->rank_at_approval }}</td>
                                <td>{{ __($history->action) }}</td>
                                <td>{{ $history->created_at->format('d/m/Y H:i:s') }}</td>
                                <td>
                                    @if ($history->signature_image_path)
                                        <img src="{{ asset('storage/' . $history->signature_image_path) }}" alt="Chữ ký" style="max-width: 80px;">
                                    @else
                                        Không có
                                    @endif
                                </td>
                                <td>{{ $history->comment ?? 'Không có' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">Chưa có lịch sử duyệt nào cho phiếu này.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-end">
        <a href="{{ route('users.pdf-requests.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách PDF
        </a>
    </div>

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

    <div class="modal fade" id="approvePdfModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận Phê duyệt Phiếu PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('users.pdf-approvals.approve', $pdfPurchaseRequest->id) }}">
                    @csrf
                    <div class="modal-body">
                        <p>Bạn có chắc chắn muốn phê duyệt phiếu PDF này không?</p>
                        <div class="mb-3">
                            <label for="approve_pdf_comment" class="form-label">Ghi chú (tùy chọn):</label>
                            <textarea class="form-control" id="approve_pdf_comment" name="comment" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-success">Xác nhận Duyệt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectPdfModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lý do từ chối Phiếu PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('users.pdf-approvals.reject', $pdfPurchaseRequest->id) }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="reject_pdf_comment" class="form-label">Vui lòng nhập lý do từ chối (bắt buộc):</label>
                            <textarea class="form-control" id="reject_pdf_comment" name="comment" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger">Xác nhận Từ chối</button>
                    </div>
                </form>
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
