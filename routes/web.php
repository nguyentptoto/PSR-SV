<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Customer\PurchaseRequestController;
use App\Http\Controllers\Customer\ApprovalController; // Controller cho phê duyệt Excel
use App\Http\Controllers\Customer\PdfPurchaseRequestController;
use App\Http\Controllers\Customer\PdfApprovalController; // THÊM DÒNG NÀY: Controller cho phê duyệt PDF
use App\Http\Controllers\SupportController;


// --- ROUTE CÔNG KHAI ---
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');



// --- CÁC ROUTE CẦN ĐĂNG NHẬP ---
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');

    // --- NHÓM ROUTE CHO NGƯỜI DÙNG (CUSTOMER) ---
    Route::prefix('users')->name('users.')->group(function () {

        // Các route export cho một phiếu đề nghị cụ thể (Excel)
        Route::get('purchase-requests/{purchaseRequest}/export', [PurchaseRequestController::class, 'exportExcel'])->name('purchase-requests.export');
        Route::get('purchase-requests/{purchaseRequest}/export-pdf', [PurchaseRequestController::class, 'exportPdf'])->name('purchase-requests.export.pdf');

        // ROUTE CHO VIỆC IMPORT VÀ XEM TRƯỚC (Ajax POST)
        Route::post('purchase-requests/import-excel-process', [PurchaseRequestController::class, 'importPreview'])->name('purchase-requests.import-excel-process');
        // ROUTE ĐỂ HIỂN THỊ TRANG XEM TRƯỚC (GET)
        Route::get('purchase-requests/import-preview', [PurchaseRequestController::class, 'showImportPreview'])->name('purchase-requests.import-preview');
        // ROUTE ĐỂ TẠO CÁC PHIẾU TỪ DỮ LIỆU ĐÃ XEM TRƯỚC (Ajax POST)
        Route::post('purchase-requests/create-from-import', [PurchaseRequestController::class, 'createFromImport'])->name('purchase-requests.create-from-import');

        // === CÁC ROUTE CHO PDF Purchase Request (Controller MỚI) ===
        Route::get('pdf-requests', [PdfPurchaseRequestController::class, 'index'])->name('pdf-requests.index'); // Route Index cho PDF PR
        Route::get('pdf-requests/create', [PdfPurchaseRequestController::class, 'create'])->name('pdf-requests.create');
        Route::post('pdf-requests/store', [PdfPurchaseRequestController::class, 'store'])->name('pdf-requests.store');
        Route::get('pdf-requests/{pdfPurchaseRequest}/preview-sign', [PdfPurchaseRequestController::class, 'previewSign'])->name('pdf-requests.preview-sign');
        Route::post('pdf-requests/{pdfPurchaseRequest}/sign-submit', [PdfPurchaseRequestController::class, 'signAndSubmit'])->name('pdf-requests.sign-submit');
        Route::get('pdf-requests/{pdfPurchaseRequest}/view-file', [PdfPurchaseRequestController::class, 'viewFile'])->name('pdf-requests.view-file');

        // THÊM CÁC ROUTE SHOW, EDIT, UPDATE, DESTROY CHO PDF PR
        Route::get('pdf-requests/{pdfPurchaseRequest}/show', [PdfPurchaseRequestController::class, 'show'])->name('pdf-requests.show');
        Route::get('pdf-requests/{pdfPurchaseRequest}/edit', [PdfPurchaseRequestController::class, 'edit'])->name('pdf-requests.edit');
        Route::put('pdf-requests/{pdfPurchaseRequest}', [PdfPurchaseRequestController::class, 'update'])->name('pdf-requests.update');
        Route::delete('pdf-requests/{pdfPurchaseRequest}', [PdfPurchaseRequestController::class, 'destroy'])->name('pdf-requests.destroy');


        Route::post('purchase-requests/bulk-export-pdf', [PurchaseRequestController::class, 'bulkExportPdf'])->name('purchase-requests.bulk-export-pdf');


        // Route resource cho quản lý phiếu đề nghị (phải để sau các route tùy chỉnh của resource)
        Route::resource('purchase-requests', PurchaseRequestController::class);

        // Nhóm route cho việc phê duyệt (Excel)
        Route::prefix('approvals')->name('approvals.')->group(function () {
            Route::get('/', [ApprovalController::class, 'index'])->name('index'); // Danh sách chờ duyệt Excel
            Route::get('/history', [ApprovalController::class, 'history'])->name('history'); // Lịch sử duyệt Excel
            Route::post('/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('bulk-approve');
            Route::post('/{purchaseRequest}/assign', [ApprovalController::class, 'assign'])->name('assign');
            Route::post('/{purchaseRequest}/approve', [ApprovalController::class, 'approve'])->name('approve');
            Route::post('/{purchaseRequest}/reject', [ApprovalController::class, 'reject'])->name('reject');
        });

        // THÊM NHÓM ROUTE MỚI CHO PHÊ DUYỆT PDF
        Route::prefix('pdf-approvals')->name('pdf-approvals.')->group(function () {
            Route::get('/', [PdfApprovalController::class, 'index'])->name('index'); // Danh sách chờ duyệt PDF
            Route::get('/history', [PdfApprovalController::class, 'history'])->name('history'); // Lịch sử duyệt PDF
            Route::post('/bulk-approve', [PdfApprovalController::class, 'bulkApprove'])->name('bulk-approve');
            Route::post('/{pdfPurchaseRequest}/approve', [PdfApprovalController::class, 'approve'])->name('approve');
            Route::post('/{pdfPurchaseRequest}/reject', [PdfApprovalController::class, 'reject'])->name('reject');
        });
    });

    // --- NHÓM ROUTE CHO ADMIN ---
    Route::prefix('admin')->name('admin.')->group(function () {
        // Route cho export danh sách người dùng
        Route::get('users/export', [UserController::class, 'export'])->name('users.export');

        // Route cho import danh sách người dùng
        Route::get('users/import', [UserController::class, 'showImportForm'])->name('users.import.show');
        Route::post('users/import', [UserController::class, 'handleImport'])->name('users.import.handle');

        // Route toggle trạng thái người dùng
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');

        // Route resource phải để sau cùng
        Route::resource('users', UserController::class);
    });
});
