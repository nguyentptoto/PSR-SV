@extends('admin')
@section('title', 'Phiếu Duyệt (PDF)')

@section('content')
<div class="card card-outline card-danger">
    <div class="card-header"><h3 class="card-title">Lọc danh sách</h3></div>
    <div class="card-body">
        <form id="filter-form" method="GET" action="{{ route('users.pdf-approvals.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="pia_code" class="form-label">Mã Phiếu</label>
                    <input type="text" class="form-control" id="pia_code" name="pia_code" value="{{ request('pia_code') }}">
                </div>
                <div class="col-md-4">
                    <label for="requester_id" class="form-label">Người tạo</label>
                    <select class="form-select" id="requester_id" name="requester_id">
                        <option value="">-- Tất cả --</option>
                        @foreach($requesters as $requester)
                            <option value="{{ $requester->id }}" {{ request('requester_id') == $requester->id ? 'selected' : '' }}>
                                {{ $requester->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="section_id" class="form-label">Phòng ban</label>
                    <select class="form-select" id="section_id" name="section_id">
                        <option value="">-- Tất cả --</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}" {{ request('section_id') == $section->id ? 'selected' : '' }}>
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 text-end mt-3">
                    <button type="submit" class="btn btn-info">Lọc</button>
                    <a href="{{ route('users.pdf-approvals.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Danh sách phiếu PDF cần xử lý</h3>
    </div>
    <form action="{{ route('users.pdf-approvals.bulk-approve') }}" method="POST" id="bulk-approve-form">
        @csrf
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{!! session('error') !!}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="mb-3">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkApprovePdfModal">
                    <i class="bi bi-check2-square"></i> Duyệt các mục đã chọn
                </button>
                    <a href="#" class="btn btn-info" id="bulk-preview-btn">
                            <i class="fas fa-eye"></i> Xem trước hàng loạt
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
                        <i class="bi bi-x-square"></i> Từ chối các mục đã chọn
                    </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10px;"><input type="checkbox" id="check-all-pdf"></th>
                            <th>Mã Phiếu</th>
                            <th>Người tạo</th>
                            <th>Phòng ban</th>
                            <th>Trạng thái hiện tại</th>
                            {{-- BỔ SUNG CỘT MỚI --}}
                            <th>Yêu cầu Giám đốc duyệt</th>
                            <th>Ngày tạo</th>
                            <th style="width: 250px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paginatedPdfRequests as $pdfPr)
                            <tr>
                                <td>
                                    <input type="checkbox" name="request_ids[]" class="request-checkbox-pdf"
                                            value="{{ $pdfPr->id }}">
                                </td>
                                <td>{{ $pdfPr->pia_code }}</td>
                                <td>{{ $pdfPr->requester->name ?? 'N/A' }}</td>
                                <td>{{ $pdfPr->requester->sections->first()->name ?? 'N/A' }}</td>
                                <td>
                                    @php
                                        $statusClass = '';
                                        switch ($pdfPr->status) {
                                            case 'pending_approval': $statusClass = 'badge badge-warning'; break;
                                            case 'approved': $statusClass = 'badge badge-success'; break;
                                            case 'rejected': $statusClass = 'badge badge-danger'; break;
                                            case 'completed': $statusClass = 'badge badge-info'; break;
                                            default: $statusClass = 'badge badge-secondary'; break;
                                        }
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ __($pdfPr->status) }} (Cấp {{ $pdfPr->current_rank_level }})</span>
                                </td>
                                {{-- BỔ SUNG CỘT DỮ LIỆU MỚI --}}
                                <td>
                                    @if($pdfPr->requires_director_approval)
                                        <span class="badge badge-danger">Có</span>
                                    @else
                                        <span class="badge badge-success">Không</span>
                                    @endif
                                </td>
                                <td>{{ $pdfPr->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('users.pdf-requests.show', $pdfPr->id) }}" class="btn btn-sm btn-info" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i> Xem
                                    </a>
                                     @if ($pdfPr->signed_pdf_path)
                                        <a href="{{ asset('storage/' . $pdfPr->signed_pdf_path) }}" target="_blank"
                                            class="btn btn-secondary btn-sm" title="Xem PDF đã ký">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-success"
                                                data-bs-toggle="modal" data-bs-target="#approvePdfModal"
                                                data-action-url="{{ route('users.pdf-approvals.approve', $pdfPr->id) }}"
                                                data-request-id="{{ $pdfPr->id }}">Duyệt</button>
                                    <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" data-bs-target="#rejectPdfModal"
                                                data-action-url="{{ route('users.pdf-approvals.reject', $pdfPr->id) }}"
                                                data-request-id="{{ $pdfPr->id }}">Từ chối</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">Không có phiếu PDF nào cần xử lý.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $paginatedPdfRequests->withQueryString()->links() }}
            </div>
        </div>
    </form>
</div>
 {{-- Modal từ chối hàng loạt --}}
    <div class="modal fade" id="bulkRejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận Từ chối Hàng Loạt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="bulk-reject-form" method="POST" action="{{ route('users.pdf-approvals.bulk-reject') }}">
                    @csrf
                    <div class="modal-body">
                        <p>Bạn có chắc chắn muốn từ chối tất cả các phiếu đã chọn không?</p>
                        <div class="mb-3">
                            <label for="bulk_reject_comment" class="form-label">Lý do từ chối (bắt buộc):</label>
                            <textarea class="form-control" id="bulk_reject_comment" name="comment" rows="3" required></textarea>
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
<div class="modal fade" id="bulkApprovePdfModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận Duyệt Hàng Loạt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulk-approve-pdf-form" method="POST" action="{{ route('users.pdf-approvals.bulk-approve') }}">
                @csrf
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn phê duyệt tất cả <strong id="selected-pdf-count">0</strong> phiếu đã chọn?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">Xác nhận Duyệt</button>
                </div>
            </form>
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
            <form id="approve-pdf-form" method="POST" action="">
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
            <form id="reject-pdf-form" method="POST" action="">
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
    $(document).ready(function() {
        // Hàm chung để xử lý modal
        function setupApprovalModal(modalId, formId) {
            $(modalId).on('show.bs.modal', function (event) {
                let button = $(event.relatedTarget);
                let actionUrl = button.data('action-url');
                let form = $(formId);
                form.attr('action', actionUrl);
            });
        }

        // Setup modals cho PDF approvals
        setupApprovalModal('#approvePdfModal', '#approve-pdf-form');
        setupApprovalModal('#rejectPdfModal', '#reject-pdf-form');

        // Checkbox cho Duyệt hàng loạt
        $('#check-all-pdf').on('click', function() {
            $('.request-checkbox-pdf').prop('checked', $(this).prop('checked'));
        });

        $('.request-checkbox-pdf').on('click', function() {
            if (!$(this).prop('checked')) {
                $('#check-all-pdf').prop('checked', false);
            }
        });

        // Xử lý modal duyệt hàng loạt
        $('#bulkApprovePdfModal').on('show.bs.modal', function (event) {
            const selectedCount = $('input.request-checkbox-pdf:checked').length;

            $('#bulk-approve-pdf-form').find('input[name="request_ids[]"]').remove();

            if (selectedCount === 0) {
                alert('Vui lòng chọn ít nhất một phiếu để duyệt.');
                event.preventDefault();
                return;
            }

            $('#selected-pdf-count').text(selectedCount);

            $('input.request-checkbox-pdf:checked').each(function() {
                $('#bulk-approve-pdf-form').append(
                    $('<input>').attr('type', 'hidden').attr('name', 'request_ids[]').val($(this).val())
                );
            });
        });
        $('#bulk-preview-btn').on('click', function(event) {
                const selectedIds = $('input.request-checkbox-pdf:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedIds.length === 0) {
                    alert('Vui lòng chọn ít nhất một phiếu để xem trước.');
                    event.preventDefault();
                    return;
                }

                const url = '{{ route('users.pdf-approvals.bulk-preview') }}' + '?ids[]=' + selectedIds
                    .join('&ids[]=');
                 window.open(url, '_blank'); // SỬA ĐỔI Ở ĐÂY
                event.preventDefault();
            });
              $('#bulkRejectModal').on('show.bs.modal', function(event) {
                const selectedCount = $('input.request-checkbox-pdf:checked').length;
                const form = $('#bulk-reject-form');

                form.find('input[name="request_ids[]"]').remove();

                if (selectedCount === 0) {
                    alert('Vui lòng chọn ít nhất một phiếu để từ chối.');
                    event.preventDefault();
                    return;
                }

                $('input.request-checkbox-pdf:checked').each(function() {
                    form.append(
                        $('<input>').attr('type', 'hidden').attr('name', 'request_ids[]').val($(
                            this).val())
                    );
                });
            });
    });
</script>
@endpush
