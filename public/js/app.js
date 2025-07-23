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



// Phê duyệt hàng loạt
$(document).ready(function () {
    // Script cho chức năng chọn tất cả
    $('#check-all').on('click', function () {
        $('.request-checkbox').prop('checked', $(this).prop('checked'));
    });
    $('.request-checkbox').on('click', function () {
        if (!$(this).prop('checked')) {
            $('#check-all').prop('checked', false);
        }
    });

    // ✅ SỬA ĐỔI: Logic cho modal tùy chỉnh
    const bulkModal = $('#bulkApproveModalOverlay');

    // Mở modal
    $('#bulk-approve-trigger-btn').on('click', function () {
        const selectedCount = $('input.request-checkbox:checked').length;
        if (selectedCount === 0) {
            alert('Vui lòng chọn ít nhất một phiếu để duyệt.');
            return;
        }
        $('#selected-count').text(selectedCount);
        bulkModal.css('display', 'flex');
        setTimeout(() => bulkModal.addClass('show'), 10);
    });

    // Đóng modal
    function closeModal() {
        bulkModal.removeClass('show');
        setTimeout(() => bulkModal.css('display', 'none'), 300);
    }

    $('#close-bulk-modal-btn').on('click', closeModal);
    $('#cancel-bulk-approve-btn').on('click', closeModal);

    // Khi nhấn nút xác nhận, gửi form
    $('#confirm-bulk-approve-btn').on('click', function () {
        $('#bulk-approve-form').submit();
    });
});


// BỔ sung css












window.addEventListener('load', () => {
    if (document.documentElement.classList.contains('theme-animated')) {
        try {
            $('.login-page').ripples({
                resolution: 512,
                dropRadius: 20,
                perturbance: 0.04,
                interactive: false
            });
        } catch (e) { console.error("Lỗi gợn sóng:", e); }

        const cursorDot = document.querySelector('.cursor-dot');
        const cursorOutline = document.querySelector('.cursor-outline');
        window.addEventListener('mousemove', e => {
            cursorDot.style.left = `${e.clientX}px`;
            cursorDot.style.top = `${e.clientY}px`;
            requestAnimationFrame(() => {
                cursorOutline.style.left = `${e.clientX}px`;
                cursorOutline.style.top = `${e.clientY}px`;
            });
        });
        document.querySelectorAll('a, button, input').forEach(el => {
            el.addEventListener('mouseenter', () => cursorOutline.classList.add('hover'));
            el.addEventListener('mouseleave', () => cursorOutline.classList.remove('hover'));
        });

        const canvas = document.getElementById('fish-canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let fishArray = [], bubbleArray = [], lotusArray = [], effectArray = [], foodArray = [];
        const isNight = document.documentElement.classList.contains('theme-night');

        class Bubble {
            constructor() { this.reset(); }
            reset() { this.x = Math.random() * canvas.width; this.y = canvas.height + Math.random() * 100; this.radius = Math.random() * 3 + 1; this.speed = Math.random() * 2 + 1; }
            update() { this.y -= this.speed; if (this.y < -10) { this.reset(); } }
            draw() { ctx.beginPath(); ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2); ctx.fillStyle = 'rgba(255, 255, 255, 0.3)'; ctx.fill(); }
        }

        class LotusLeaf {
            constructor(x, y, scale) { this.position = new Vector(x, y); this.scale = scale; this.radius = 80 * scale; }
            draw() {
                ctx.save();
                ctx.translate(this.position.x, this.position.y);
                ctx.scale(this.scale, this.scale);
                ctx.beginPath(); ctx.ellipse(5, 5, 80, 80, 0, 0, Math.PI * 2); ctx.fillStyle = isNight ? 'rgba(0,0,0,0.3)' : 'rgba(0,0,0,0.1)'; ctx.fill();
                ctx.beginPath(); ctx.arc(0, 0, 80, 0.2 * Math.PI, 1.9 * Math.PI); ctx.lineTo(0, 0); ctx.closePath(); ctx.fillStyle = isNight ? '#1e605a' : '#2a9d8f'; ctx.fill();
                ctx.strokeStyle = isNight ? 'rgba(0,0,0,0.4)' : 'rgba(0,0,0,0.2)'; ctx.lineWidth = 2;
                for (let i = 0; i < 8; i++) { ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(Math.cos(i * Math.PI / 4) * 75, Math.sin(i * Math.PI / 4) * 75); ctx.stroke(); }
                ctx.restore();
            }
        }

        class Petal {
            constructor() { this.reset(); this.type = Math.random() > 0.5 ? 'sakura' : 'lotus'; }
            reset() { this.x = Math.random() * canvas.width; this.y = -Math.random() * canvas.height; this.size = Math.random() * 8 + 5; this.speed = Math.random() * 1 + 1; this.rotation = Math.random() * Math.PI * 2; this.spin = Math.random() * 0.04 - 0.02; this.sway = Math.random() * Math.PI * 2; }
            update() { this.y += this.speed; this.x += Math.sin(this.sway); this.sway += 0.03; this.rotation += this.spin; if (this.y > canvas.height + this.size) this.reset(); }
            draw() {
                ctx.save(); ctx.translate(this.x, this.y); ctx.rotate(this.rotation);
                if (this.type === 'sakura') {
                    ctx.fillStyle = 'rgba(255, 192, 203, 0.8)'; ctx.beginPath(); ctx.moveTo(0, -this.size); ctx.quadraticCurveTo(this.size, 0, 0, this.size); ctx.quadraticCurveTo(-this.size, 0, 0, -this.size); ctx.closePath(); ctx.fill();
                } else {
                    ctx.fillStyle = 'rgba(255, 240, 245, 0.9)'; ctx.beginPath(); ctx.moveTo(0, -this.size); ctx.bezierCurveTo(this.size, -this.size / 2, this.size / 2, this.size, 0, this.size); ctx.bezierCurveTo(-this.size / 2, this.size, -this.size, -this.size / 2, 0, -this.size); ctx.closePath(); ctx.fill();
                }
                ctx.restore();
            }
        }

        class Lantern {
            constructor() { this.reset(); }
            reset() { this.x = Math.random() * canvas.width; this.y = canvas.height + Math.random() * 100; this.size = Math.random() * 15 + 10; this.speed = Math.random() * 0.5 + 0.2; this.sway = Math.random() * Math.PI * 2; this.alpha = 0.5 + Math.random() * 0.5; }
            update() { this.y -= this.speed; this.x += Math.sin(this.sway) * 0.5; this.sway += 0.02; if (this.y < -this.size * 2) this.reset(); }
            draw() {
                ctx.save(); ctx.translate(this.x, this.y);
                const gradient = ctx.createRadialGradient(0, 0, 1, 0, 0, this.size);
                gradient.addColorStop(0, `rgba(255, 220, 100, ${this.alpha})`); gradient.addColorStop(1, `rgba(255, 165, 0, ${this.alpha * 0.5})`);
                ctx.fillStyle = gradient; ctx.beginPath(); ctx.arc(0, 0, this.size, 0, Math.PI * 2); ctx.fill();
                ctx.shadowColor = 'orange'; ctx.shadowBlur = 20; ctx.fill(); ctx.shadowBlur = 0;
                ctx.restore();
            }
        }

        class FoodParticle {
            constructor(x, y) { this.position = new Vector(x, y); this.radius = 2; this.speed = Math.random() * 0.5 + 0.5; this.lifespan = 255; this.eaten = false; }
            update() { this.position.y += this.speed; this.lifespan -= 1; }
            draw() { ctx.beginPath(); ctx.arc(this.position.x, this.position.y, this.radius, 0, Math.PI * 2); ctx.fillStyle = `rgba(139, 69, 19, ${this.lifespan / 255})`; ctx.fill(); }
        }

        class Vector {
            constructor(x = 0, y = 0) { this.x = x; this.y = y; }
            add(v) { this.x += v.x; this.y += v.y; return this; }
            sub(v) { this.x -= v.x; this.y -= v.y; return this; }
            mult(n) { this.x *= n; this.y *= n; return this; }
            div(n) { this.x /= n; this.y /= n; return this; }
            mag() { return Math.sqrt(this.x * this.x + this.y * this.y); }
            limit(max) { const mSq = this.x * this.x + this.y * this.y; if (mSq > max * max) { this.div(Math.sqrt(mSq)).mult(max); } return this; }
            setMag(n) { const len = this.mag(); if (len !== 0) { this.mult(n / len); } return this; }
            static sub(v1, v2) { return new Vector(v1.x - v2.x, v1.y - v2.y); }
            static random2D() { const angle = Math.random() * 2 * Math.PI; return new Vector(Math.cos(angle), Math.sin(angle)); }
        }

        class Fish {
            constructor() {
                this.position = new Vector(Math.random() * canvas.width, Math.random() * canvas.height);
                this.velocity = Vector.random2D().setMag(Math.random() * 1.5 + 1);
                this.acceleration = new Vector();
                this.maxForce = 0.08;
                this.maxSpeed = 3.7;
                this.perceptionRadius = 100;
                this.baseSize = Math.random() * 8 + 6;
                this.bodyLength = Math.floor(this.baseSize * 2.5);
                this.body = [];
                for (let i = 0; i < this.bodyLength; i++) { this.body.push({ ...this.position }); }
                const koiColors = ['hsl(5, 80%, 60%)', 'hsl(35, 90%, 60%)', 'hsl(0, 0%, 95%)'];
                this.color = koiColors[Math.floor(Math.random() * koiColors.length)];
            }
            edges() { const padding = 50; if (this.position.x > canvas.width + padding) this.position.x = -padding; else if (this.position.x < -padding) this.position.x = canvas.width + padding; if (this.position.y > canvas.height + padding) this.position.y = -padding; else if (this.position.y < -padding) this.position.y = canvas.height + padding; }
            align(fishes) { let s = new Vector(); let t = 0; for (let o of fishes) { const d = Math.hypot(this.position.x - o.position.x, this.position.y - o.position.y); if (o !== this && d < this.perceptionRadius) { s.add(o.velocity); t++; } } if (t > 0) { s.div(t).setMag(this.maxSpeed).sub(this.velocity).limit(this.maxForce); } return s; }
            cohesion(fishes) { let s = new Vector(); let t = 0; for (let o of fishes) { const d = Math.hypot(this.position.x - o.position.x, this.position.y - o.position.y); if (o !== this && d < this.perceptionRadius) { s.add(o.position); t++; } } if (t > 0) { s.div(t).sub(this.position).setMag(this.maxSpeed).sub(this.velocity).limit(this.maxForce); } return s; }
            separation(fishes, obstacles) { let s = new Vector(); let t = 0; for (let o of fishes) { const d = Math.hypot(this.position.x - o.position.x, this.position.y - o.position.y); if (o !== this && d < this.perceptionRadius) { let diff = Vector.sub(this.position, o.position); if (d > 0) diff.div(d * d); s.add(diff); t++; } } for (let o of obstacles) { const d = Math.hypot(this.position.x - o.position.x, this.position.y - o.position.y); if (d < o.radius) { let diff = Vector.sub(this.position, o.position); if (d > 0) diff.div(d); s.add(diff); t++; } } if (t > 0) { s.div(t).setMag(this.maxSpeed).sub(this.velocity).limit(this.maxForce); } return s; }
            flee(target) { let d = Vector.sub(this.position, target); d.setMag(this.maxSpeed * 2); let s = Vector.sub(d, this.velocity); s.limit(this.maxForce * 5); return s; }
            seek(target) { let d = Vector.sub(target, this.position); d.setMag(this.maxSpeed); let s = Vector.sub(d, this.velocity); s.limit(this.maxForce * 2); return s; }
            behave(fishes, clickPoint, obstacles, food) {
                this.acceleration.mult(0);
                let nearestFood = null;
                let record = Infinity;
                for (let f of food) {
                    let d = Math.hypot(this.position.x - f.position.x, this.position.y - f.position.y);
                    if (d < record) { record = d; nearestFood = f; }
                }
                if (nearestFood && record < this.perceptionRadius * 2) {
                    let seekForce = this.seek(nearestFood.position);
                    this.acceleration.add(seekForce);
                    if (record < 5) { nearestFood.eaten = true; }
                } else if (clickPoint) {
                    const d = Math.hypot(this.position.x - clickPoint.x, this.position.y - clickPoint.y);
                    if (d < this.perceptionRadius * 2.5) {
                        let fleeForce = this.flee(clickPoint);
                        this.acceleration.add(fleeForce);
                    }
                } else {
                    let a = this.align(fishes); let c = this.cohesion(fishes); let s = this.separation(fishes, obstacles).mult(1.5);
                    this.acceleration.add(a); this.acceleration.add(c); this.acceleration.add(s);
                }
            }
            update() { this.position.add(this.velocity); this.velocity.add(this.acceleration).limit(this.maxSpeed); this.body.unshift({ ...this.position }); this.body.pop(); }
            draw() { this.body.forEach((b, index) => { let size; if (index < this.bodyLength / 6) { size = this.baseSize + index * 1.5; } else { size = this.baseSize * 1.8 - index; } if (size < 0) size = 0; const alpha = (this.bodyLength - index) / this.bodyLength * (isNight ? 0.6 : 0.9); let c = this.color.match(/(\d+)/g); let h = c[0], s = c[1], l = c[2]; ctx.beginPath(); ctx.arc(b.x, b.y, size, 0, Math.PI * 2); ctx.fillStyle = `hsla(${h}, ${s}%, ${l}%, ${alpha})`; ctx.fill(); }); }
        }

        function initScene() {
            fishArray = []; bubbleArray = []; lotusArray = []; effectArray = []; foodArray = [];
            let numFish = (canvas.width * canvas.height) / 35000; if (numFish > 30) numFish = 30;
            for (let i = 0; i < numFish; i++) fishArray.push(new Fish());
            for (let i = 0; i < 20; i++) bubbleArray.push(new Bubble());
            if (isNight) {
                for (let i = 0; i < 20; i++) effectArray.push(new Lantern());
            } else {
                for (let i = 0; i < 25; i++) effectArray.push(new Petal());
            }
            lotusArray.push(new LotusLeaf(canvas.width * 0.2, canvas.height * 0.3, 1.2));
            lotusArray.push(new LotusLeaf(canvas.width * 0.8, canvas.height * 0.7, 1));
            lotusArray.push(new LotusLeaf(canvas.width * 0.5, canvas.height * 0.8, 0.8));
        }

        let clickPoint = null; let clickTimer = 0;

        function animateScene() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const lightGradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            if (isNight) {
                lightGradient.addColorStop(0, 'rgba(173, 216, 230, 0.3)');
                lightGradient.addColorStop(1, 'rgba(2, 62, 138, 0.4)');
            } else {
                lightGradient.addColorStop(0, 'rgba(173, 216, 230, 0.2)');
                lightGradient.addColorStop(0.5, 'rgba(0, 119, 182, 0.1)');
                lightGradient.addColorStop(1, 'rgba(2, 62, 138, 0.3)');
            }
            ctx.fillStyle = lightGradient; ctx.fillRect(0, 0, canvas.width, canvas.height);

            if (clickTimer > 0) { clickTimer--; } else { clickPoint = null; }

            lotusArray.forEach(l => l.draw());
            foodArray = foodArray.filter(f => f.lifespan > 0 && !f.eaten);
            foodArray.forEach(f => { f.update(); f.draw(); });
            fishArray.forEach(f => { f.edges(); f.behave(fishArray, clickPoint, lotusArray, foodArray); f.update(); f.draw(); });
            bubbleArray.forEach(b => { b.update(); b.draw(); });
            effectArray.forEach(e => { e.update(); e.draw(); });

            requestAnimationFrame(animateScene);
        }

        initScene();
        animateScene();

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initScene();
        });

        canvas.addEventListener('mousedown', function (e) {
            $('.login-page').ripples('drop', e.clientX, e.clientY, 20, 0.04);
            clickPoint = new Vector(e.clientX, e.clientY);
            clickTimer = 120;
        });

            canvas.addEventListener('dblclick', function (e) {
                for (let i = 0; i < 10; i++) {
                    foodArray.push(new FoodParticle(e.clientX + Math.random() * 40 - 20, e.clientY + Math.random() * 40 - 20));
                }
            });
        }
    });

document.getElementById('theme-toggle').addEventListener('click', function () {
    const root = document.documentElement;
    if (root.classList.contains('theme-animated')) {
        root.classList.remove('theme-animated');
        localStorage.setItem('loginTheme', 'default');
    } else {
        root.classList.add('theme-animated');
        localStorage.setItem('loginTheme', 'animated');
    }
    window.location.reload();
});

document.getElementById('night-mode-toggle').addEventListener('click', function () {
    const root = document.documentElement;
    const icon = this.querySelector('i');
    root.classList.toggle('theme-night');
    if (root.classList.contains('theme-night')) {
        localStorage.setItem('loginNightMode', 'true');
        icon.classList.remove('bi-moon-stars-fill');
        icon.classList.add('bi-brightness-high-fill');
    } else {
        localStorage.setItem('loginNightMode', 'false');
        icon.classList.remove('bi-brightness-high-fill');
        icon.classList.add('bi-moon-stars-fill');
    }
    window.location.reload();
});

if (document.documentElement.classList.contains('theme-night')) {
    const icon = document.getElementById('night-mode-toggle').querySelector('i');
    icon.classList.remove('bi-moon-stars-fill');
    icon.classList.add('bi-brightness-high-fill');
}













document.addEventListener('DOMContentLoaded', function () {
    const approveModal = document.getElementById('approveModal');
    if (approveModal) {
        approveModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const actionUrl = button.getAttribute('data-action-url');
            const form = approveModal.querySelector('#approve-form');
            form.setAttribute('action', actionUrl);
        });
    }

    const rejectModal = document.getElementById('rejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const actionUrl = button.getAttribute('data-action-url');
            const form = rejectModal.querySelector('#reject-form');
            form.setAttribute('action', actionUrl);
        });
    }
});



