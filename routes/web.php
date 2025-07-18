<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Customer\PurchaseRequestController;
use App\Http\Controllers\Customer\ApprovalController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\LocalizationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// --- ROUTE CÔNG KHAI ---
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Route chuyển đổi ngôn ngữ
Route::get('language/{locale}', [LocalizationController::class, 'switchLang'])->name('language.switch');

// --- CÁC ROUTE CẦN ĐĂNG NHẬP ---
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');

    // --- NHÓM ROUTE CHO NGƯỜI DÙNG (CUSTOMER) ---
    Route::prefix('users')->name('users.')->group(function() {

        // Các route export cho một phiếu đề nghị cụ thể
        Route::get('purchase-requests/{purchaseRequest}/export', [PurchaseRequestController::class, 'exportExcel'])->name('purchase-requests.export');
        Route::get('purchase-requests/{purchaseRequest}/export-pdf', [PurchaseRequestController::class, 'exportPdf'])->name('purchase-requests.export.pdf');

        // ROUTE CHO VIỆC IMPORT VÀ XEM TRƯỚC (Ajax POST)
        Route::post('purchase-requests/import-excel-process', [PurchaseRequestController::class, 'importPreview'])->name('purchase-requests.import-excel-process');
        // ROUTE ĐỂ HIỂN THỊ TRANG XEM TRƯỚC (GET)
        Route::get('purchase-requests/import-preview', [PurchaseRequestController::class, 'showImportPreview'])->name('purchase-requests.import-preview');
        // ROUTE ĐỂ TẠO CÁC PHIẾU TỪ DỮ LIỆU ĐÃ XEM TRƯỚC (Ajax POST)
        Route::post('purchase-requests/create-from-import', [PurchaseRequestController::class, 'createFromImport'])->name('purchase-requests.create-from-import');

        Route::post('purchase-requests/bulk-export-pdf', [PurchaseRequestController::class, 'bulkExportPdf'])->name('purchase-requests.bulk-export-pdf');


        // Route resource cho quản lý phiếu đề nghị (phải để sau các route tùy chỉnh của resource)
        Route::resource('purchase-requests', PurchaseRequestController::class);

        // Nhóm route cho việc phê duyệt
        Route::prefix('approvals')->name('approvals.')->group(function() {
            Route::get('/', [ApprovalController::class, 'index'])->name('index');
            Route::get('/history', [ApprovalController::class, 'history'])->name('history');
            Route::post('/bulk-approve', [ApprovalController::class, 'bulkApprove'])->name('bulk-approve');

            Route::post('/{purchaseRequest}/approve', [ApprovalController::class, 'approve'])->name('approve');
            Route::post('/{purchaseRequest}/reject', [ApprovalController::class, 'reject'])->name('reject');
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
