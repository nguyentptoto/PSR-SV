// Modal chi tiêt người dùng
document.addEventListener('DOMContentLoaded', function () {
    const userModal = document.getElementById('userModal');
    if (userModal) {
        userModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const user = JSON.parse(button.getAttribute('data-user'));

            // ✅ SỬA ĐỔI: In thông tin người dùng ra console
            console.log('User Details:', user);

            const modalTitle = userModal.querySelector('.modal-title');
            const modalBody = userModal.querySelector('#userModalBody');

            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modalTitle.textContent = 'Chi tiết người dùng: ' + user.name;

            let detailsHtml = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>ID:</strong> ${user.id || 'N/A'}</p>
                        <p><strong>Tên:</strong> ${user.name || 'N/A'}</p>
                        <p><strong>Email:</strong> ${user.email || 'N/A'}</p>
                        <p><strong>Mã nhân viên:</strong> ${user.employee_id || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>PRS ID:</strong> ${user.prs_id || 'Chưa có'}</p>
                        <p><strong>Chi nhánh chính:</strong> ${user.main_branch ? user.main_branch.name : 'Chưa có'}</p>
                        <p><strong>Trạng thái:</strong> ${user.status == 1 ? '<span class="badge text-bg-success">Hoạt động</span>' : '<span class="badge text-bg-danger">Khóa</span>'}</p>
                        <p><strong>Ngày tạo:</strong> ${new Date(user.created_at).toLocaleString('vi-VN')}</p>
                    </div>
                </div>
            `;

            if (user.signature_image_path) {
                detailsHtml += `
                    <hr>
                    <h5>Chữ ký</h5>
                    <img src="/storage/${user.signature_image_path}" alt="Chữ ký" class="img-fluid rounded" style="max-height: 150px; border: 1px solid #dee2e6;">
                `;
            }

            if (user.sections && user.sections.length > 0) {
                detailsHtml += '<hr><h5>Phòng ban chuyên môn</h5><ul class="list-group list-group-flush">';
                user.sections.forEach(section => {
                    detailsHtml += `<li class="list-group-item py-1">${section.name || 'N/A'}</li>`;
                });
                detailsHtml += '</ul>';
            }

            if (user.assignments && user.assignments.length > 0) {
                detailsHtml += `
                    <hr>
                    <h5>Quyền hạn được gán</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Nhóm chức năng</th>
                                    <th>Chi nhánh</th>
                                    <th>Cấp bậc duyệt</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                user.assignments.forEach(assignment => {
                    detailsHtml += `
                        <tr>
                            <td>${assignment.group ? assignment.group.name : 'N/A'}</td>
                            <td>${assignment.branch ? assignment.branch.name : 'N/A'}</td>
                            <td>${assignment.approval_rank ? assignment.approval_rank.name : 'Không có'}</td>
                        </tr>
                    `;
                });
                detailsHtml += '</tbody></table></div>';
            }

            setTimeout(() => {
                modalBody.innerHTML = detailsHtml;
            }, 200);
        });
    }
});


// Select nhiều phòng ban

$(document).ready(function () {
    // --- Code khởi tạo Select2 (giữ nguyên) ---
    $('#sections-select2').select2({
        theme: 'bootstrap-5',
        placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
        allowClear: true
    });

    // --- CODE MỚI CHO CÁC NÚT BẤM ---

    // Khi nhấn nút "Chọn tất cả"
    $('#select-all-sections').on('click', function () {
        // Lấy tất cả các option và chọn chúng
        $("#sections-select2 > option").prop("selected", true);
        // Kích hoạt sự kiện change để Select2 cập nhật giao diện
        $("#sections-select2").trigger("change");
    });

    // Khi nhấn nút "Bỏ chọn tất cả"
    $('#deselect-all-sections').on('click', function () {
        // Bỏ chọn tất cả các option
        $("#sections-select2 > option").prop("selected", false);
        // Kích hoạt sự kiện change
        $("#sections-select2").trigger("change");
    });
});



// Xử lý sự kiện kích hoạt/tạm ngưng người dùng
document.addEventListener('DOMContentLoaded', function () {
    // Bắt sự kiện click trên tất cả các nút có class 'toggle-status-btn'
    document.querySelectorAll('.toggle-status-btn').forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault(); // Ngăn form submit ngay lập tức

            const form = this.closest('form'); // Tìm form cha gần nhất
            const action = this.dataset.action; // Lấy hành động (disable/enable)
            const userName = form.closest('tr').querySelector('td:nth-child(2)').textContent; // Lấy tên người dùng từ bảng

            let confirmText = '';
            let confirmButtonColor = '';

            if (action === 'disable') {
                confirmText = `Bạn có chắc chắn muốn vô hiệu hóa tài khoản của <strong>${userName}</strong> không?`;
                confirmButtonColor = '#f0ad4e'; // Màu vàng
            } else {
                confirmText = `Bạn có chắc chắn muốn kích hoạt lại tài khoản của <strong>${userName}</strong> không?`;
                confirmButtonColor = '#5cb85c'; // Màu xanh
            }

            Swal.fire({
                title: 'Xác nhận hành động',
                html: confirmText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#d33',
                confirmButtonText: 'Có, chắc chắn!',
                cancelButtonText: 'Hủy bỏ'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Nếu người dùng xác nhận, submit form
                }
            });
        });
    });

});

// Export dữ liệu người dùng

$(document).ready(function () {
    // Hàm cập nhật URL cho nút Export
    function updateExportUrl() {
        // Lấy tất cả dữ liệu từ form lọc có id="filter-form"
        var query = $('#filter-form').serialize();
        // Tạo url mới với các tham số lọc
        var url = "{{ route('admin.users.export') }}" + "?" + query;
        // Gán url mới cho nút Export
        $('#export-button').attr('href', url);
    }

    // Lắng nghe sự kiện thay đổi trên các ô input/select của form lọc
    $('#filter-form input, #filter-form select').on('change keyup', function () {
        updateExportUrl();
    });

    // Cập nhật URL ngay khi tải trang
    updateExportUrl();
});


// Select cho chức vụ
$(document).ready(function () {
    // Khởi tạo Select2 cho Chức vụ
    $('#job-title-select2').select2({
        placeholder: "-- Chọn chức vụ --",
        allowClear: true // Tùy chọn: Thêm nút (x) để xóa lựa chọn
    });

    // Giả sử bạn đã có code này cho Phòng ban
    $('#sections-select2').select2({
        placeholder: "Chọn phòng ban chuyên môn",
    });
});




