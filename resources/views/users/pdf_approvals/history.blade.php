@extends('admin')
@section('title', 'Lịch Sử Duyệt (PDF)')

@section('content')
<div class="card card-outline card-danger">
    <div class="card-header"><h3 class="card-title">Lọc danh sách</h3></div>
    <div class="card-body">
        <form id="filter-form" method="GET" action="{{ route('users.pdf-approvals.history') }}">
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
                    <a href="{{ route('users.pdf-approvals.history') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lịch sử duyệt phiếu PDF</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width: 10px;">#</th>
                        <th>Mã Phiếu</th>
                        <th>Người tạo</th>
                        <th>Phòng ban</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th style="width: 100px;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approvedPdfRequests as $pdfPr)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
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
                                <span class="{{ $statusClass }}">{{ __($pdfPr->status) }}</span>
                            </td>
                            <td>{{ $pdfPr->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('users.pdf-requests.show', $pdfPr->id) }}" class="btn btn-sm btn-info" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i> Xem
                                </a>
                                @if ($pdfPr->signed_pdf_path)
                                    <a href="{{ asset('storage/' . $pdfPr->signed_pdf_path) }}" target="_blank" class="btn btn-sm btn-success" title="Xem file đã ký">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">Không có phiếu PDF nào đã được duyệt.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">
            {{ $approvedPdfRequests->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
