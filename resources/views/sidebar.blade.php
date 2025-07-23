 <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark"> <!--begin::Sidebar Brand-->
     <div class="sidebar-brand"> <!--begin::Brand Link--> <a href="{{ route('dashboard') }}" class="brand-link">
             <!--begin::Brand Image--> <img src="{{ asset('assets/img/logo1.svg') }}" alt="Logo admin"
                 class="brand-image opacity-75 shadow"> </a> </div>
     <!--end::Sidebar Brand--> <!--begin::Sidebar Wrapper-->
     <div class="sidebar-wrapper">
         <nav class="mt-2"> <!--begin::Sidebar Menu-->
             <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
                 aria-label="Main navigation" data-accordion="false" id="navigation">
                 <!-- MỤC DÀNH CHO NGƯỜI CÓ RANK >= 1 -->

                 {{-- Kiểm tra quyền, chỉ hiển thị cho người dùng có Cấp 1 trở lên --}}
                 @can('is-level-1')
                     {{-- Thêm class 'menu-open' khi đang ở các trang liên quan đến phiếu đề nghị --}}
                     <li class="nav-item {{ request()->is('users/purchase-requests*') ? 'menu-open' : '' }}">

                         {{-- Thêm class 'active' cho link cha --}}
                         <a href="#" class="nav-link {{ request()->is('users/purchase-requests*') ? 'active' : '' }}">
                             <i class="nav-icon bi bi-file-text-fill"></i>
                             <p>
                                 Quản lý phiếu
                                 <i class="nav-arrow bi bi-chevron-right"></i>
                             </p>
                         </a>
                         <ul class="nav nav-treeview">
                             <li class="nav-item">
                                 {{-- Link đến trang danh sách phiếu --}}
                                 <a href="{{ route('users.purchase-requests.index') }}"
                                     class="nav-link {{ request()->routeIs('users.purchase-requests.index') ? 'active' : '' }}">
                                     <i class="nav-icon bi bi-circle"></i>
                                     <p>Danh sách phiếu</p>
                                 </a>
                             </li>
                             <li class="nav-item">
                                 {{-- Link đến trang tạo phiếu mới --}}
                                 <a href="{{ route('users.purchase-requests.create') }}"
                                     class="nav-link {{ request()->routeIs('users.purchase-requests.create') ? 'active' : '' }}">
                                     <i class="nav-icon bi bi-circle"></i>
                                     <p>Tạo Phiếu mới</p>
                                 </a>
                             </li>
                         </ul>
                     </li>
                 @endcan
                 <!-- ✅ SỬA ĐỔI: Menu Phê duyệt giờ là một dropdown -->
                 @can('can-approve')
                     <li class="nav-item {{ request()->is('users/approvals*') ? 'menu-open' : '' }}">
                         <a href="#" class="nav-link {{ request()->is('users/approvals*') ? 'active' : '' }}">
                             <i class="nav-icon bi bi-check2-all"></i>
                             <p>
                                 Phê duyệt
                                 <i class="nav-arrow bi bi-chevron-right"></i>
                             </p>
                         </a>
                         <ul class="nav nav-treeview">
                             <li class="nav-item">
                                 <a href="{{ route('users.approvals.index') }}"
                                     class="nav-link {{ request()->routeIs('users.approvals.index') ? 'active' : '' }}">
                                     <i class="nav-icon bi bi-circle"></i>
                                     <p>Phiếu chờ duyệt</p>
                                 </a>
                             </li>
                             <li class="nav-item">
                                 <a href="{{ route('users.approvals.history') }}"
                                     class="nav-link {{ request()->routeIs('users.approvals.history') ? 'active' : '' }}">
                                     <i class="nav-icon bi bi-circle"></i>
                                     <p>Lịch sử đã duyệt</p>
                                 </a>
                             </li>
                         </ul>
                     </li>
                 @endcan





                 <!-- MỤC CHỈ DÀNH CHO ADMIN -->
                 @can('is-admin')
                     <li class="nav-item {{ request()->is('admin/users*') ? 'menu-open' : '' }}">
                         {{-- Thêm class 'active' nếu route hiện tại bắt đầu bằng 'admin/users' --}}
                         <a href="#" class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}">
                             <i class="nav-icon bi bi-people-fill"></i> {{-- Đổi icon cho phù hợp --}}
                             <p>
                                 Quản lý người dùng
                                 <i class="nav-arrow bi bi-chevron-right"></i>
                             </p>
                         </a>
                         <ul class="nav nav-treeview">
                             <li class="nav-item">
                                 {{-- Link đến trang danh sách người dùng --}}
                                 <a href="{{ route('admin.users.index') }}"
                                     class="nav-link {{ request()->routeIs('admin.users.index') ? 'active' : '' }}">
                                     <i class="nav-icon bi bi-circle"></i>
                                     <p>Danh sách người dùng</p>
                                 </a>
                             </li>
                             <li class="nav-item">
                                 {{-- Link đến trang thêm mới người dùng --}}
                                 <a href="{{ route('admin.users.create') }}"
                                     class="nav-link {{ request()->routeIs('admin.users.create') ? 'active' : '' }}">
                                     <i class="nav-icon bi bi-circle"></i>
                                     <p>Thêm người dùng</p>
                                 </a>
                             </li>
                         </ul>
                     </li>
                 @endcan
             </ul> <!--end::Sidebar Menu-->
         </nav>
     </div> <!--end::Sidebar Wrapper-->
 </aside> <!--end::Sidebar--> <!--begin::App Main-->
