 <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark"> <!--begin::Sidebar Brand-->
     <div class="sidebar-brand"> <!--begin::Brand Link--> <a href="{{ route('dashboard') }}" class="brand-link">
             <!--begin::Brand Image--> <img src="{{ asset('assets/img/logo1.svg') }}" alt="Logo admin"
                 class="brand-image opacity-75 shadow"> </a> </div>
     <!--end::Sidebar Brand--> <!--begin::Sidebar Wrapper-->
     <div class="sidebar-wrapper">
         <nav class="mt-2"> <!--begin::Sidebar Menu-->
             <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
                 aria-label="Main navigation" data-accordion="false" id="navigation">

                 @can('is-level-1')
                 {{-- Quản lý phiếu (Excel) --}}
                 <!-- <li class="nav-item {{ request()->is('users/purchase-requests*') && !request()->is('users/pdf-requests*') ? 'menu-open' : '' }}">
                     <a href="#" class="nav-link {{ request()->is('users/purchase-requests*') && !request()->is('users/pdf-requests*') ? 'active' : '' }}">
                         <i class="nav-icon bi bi-file-text-fill"></i>
                         <p>
                             Quản lý phiếu (Excel)
                             <i class="nav-arrow bi bi-chevron-right"></i>
                         </p>
                     </a>
                     <ul class="nav nav-treeview">
                         <li class="nav-item">
                             <a href="{{ route('users.purchase-requests.index') }}"
                                 class="nav-link {{ request()->routeIs('users.purchase-requests.index') ? 'active' : '' }}">
                                 <i class="nav-icon bi bi-circle"></i>
                                 <p>Danh sách phiếu Excel</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="{{ route('users.purchase-requests.create') }}"
                                 class="nav-link {{ request()->routeIs('users.purchase-requests.create') ? 'active' : '' }}">
                                 <i class="nav-icon bi bi-circle"></i>
                                 <p>Tạo Phiếu Excel mới</p>
                             </a>
                         </li>
                     </ul>
                 </li> -->

                 {{-- Quản lý phiếu (PDF) --}}
                 <li class="nav-item {{ request()->is('users/pdf-requests*') ? 'menu-open' : '' }}">
                     <a href="#" class="nav-link {{ request()->is('users/pdf-requests*') ? 'active' : '' }}">
                         <i class="nav-icon bi bi-file-pdf"></i> {{-- Icon PDF --}}
                         <p>
                             Quản lý phiếu (PDF)
                             <i class="nav-arrow bi bi-chevron-right"></i>
                         </p>
                     </a>
                     <ul class="nav nav-treeview">
                         <li class="nav-item">
                             <a href="{{ route('users.pdf-requests.index') }}"
                                 class="nav-link {{ request()->routeIs('users.pdf-requests.index') ? 'active' : '' }}">
                                 <i class="nav-icon bi bi-circle"></i>
                                 <p>Danh sách phiếu PDF</p>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a href="{{ route('users.pdf-requests.create') }}"
                                 class="nav-link {{ request()->routeIs('users.pdf-requests.create') ? 'active' : '' }}">
                                 <i class="nav-icon bi bi-circle"></i>
                                 <p>Tạo Phiếu PDF mới</p>
                             </a>
                         </li>
                     </ul>
                 </li>
             @endcan

                 <!-- ✅ SỬA ĐỔI: Menu Phê duyệt giờ là một dropdown -->
               @can('can-approve')
    <li class="nav-item {{ request()->is('users/approvals*') || request()->is('users/pdf-approvals*') ? 'menu-open' : '' }}">
        <a href="#" class="nav-link {{ request()->is('users/approvals*') || request()->is('users/pdf-approvals*') ? 'active' : '' }}">
            <i class="nav-icon bi bi-check2-all"></i>
            <p>
                Phê duyệt
                <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
        </a>
        <ul class="nav nav-treeview">
            {{-- Menu con cho phê duyệt Excel --}}
            <!-- <li class="nav-item">
                <a href="{{ route('users.approvals.index') }}"
                   class="nav-link {{ request()->routeIs('users.approvals.index') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Phiếu Excel chờ duyệt</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('users.approvals.history') }}"
                   class="nav-link {{ request()->routeIs('users.approvals.history') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Lịch sử Excel đã duyệt</p>
                </a>
            </li> -->

            {{-- THÊM MENU CON MỚI CHO PHÊ DUYỆT PDF --}}
            <li class="nav-item">
                <a href="{{ route('users.pdf-approvals.index') }}"
                   class="nav-link {{ request()->routeIs('users.pdf-approvals.index') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle-fill"></i>
                    <p>Phiếu PDF chờ duyệt</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('users.pdf-approvals.history') }}"
                   class="nav-link {{ request()->routeIs('users.pdf-approvals.history') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-circle-fill"></i>
                    <p>Lịch sử PDF đã duyệt</p>
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
