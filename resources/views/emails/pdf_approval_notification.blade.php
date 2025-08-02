<div class="container">
        <div class="header">
            <h1>Thông báo yêu cầu duyệt phiếu</h1>
        </div>
        <div class="content">
            <p>Chào <strong>{{ $approver->name }}</strong>,</p>
            <p>
                Bạn có một phiếu đề nghị dạng PDF mới cần được duyệt.
            </p>

            <p><strong>Thông tin chi tiết:</strong></p>
            <table class="details-table">
                <tbody>
                    <tr>
                        <th>Mã phiếu (PR No.)</th>
                        <td>{{ $pdfPr->pia_code }}</td>
                    </tr>
                    <tr>
                        <th>Người yêu cầu</th>
                        <td>{{ $pdfPr->requester->name }}</td>
                    </tr>
                    <tr>
                        <th>Ngày yêu cầu</th>
                        <td>{{ $pdfPr->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @if($pdfPr->remarks)
                    <tr>
                        <th>Ghi chú</th>
                        <td>{{ $pdfPr->remarks }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>

            <a href="{{ route('approvals.show-pdf-request', $pdfPr->id) }}" class="cta-button">Xem và duyệt phiếu</a>

            <p>
                Vui lòng truy cập vào hệ thống để xem chi tiết và thực hiện phê duyệt.
            </p>
            <p>
                Trân trọng,<br>
                Hệ thống quản lý phiếu đề nghị
            </p>
        </div>
        <div class="footer">
            <p>Email này được gửi tự động. Vui lòng không trả lời.</p>
        </div>
    </div>
