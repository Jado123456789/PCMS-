<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\MainController;

// Public route - registration is the first page
Route::get('/', function () {
    return view('registration');
})->name("index");

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [MainController::class, 'index'])->name("dashboard");
    
    // Admin dashboard - only for role_id = 1
    Route::get('/admin/dashboard', [MainController::class, 'adminIndex'])->name("admin.dashboard");
    Route::get('/admin/dashboard/live', [MainController::class, 'adminDashboardLive'])->name("admin.dashboard.live");
    Route::get('/admin/customers', [MainController::class, 'adminCustomers'])->name("admin.customers");
    Route::get('/admin/devices', [MainController::class, 'adminDevices'])->name("admin.devices");
    Route::get('/admin/payments', [MainController::class, 'adminPayments'])->name("admin.payments");
    Route::get('/admin/reports', [MainController::class, 'adminReports'])->name("admin.reports");
    Route::post('/admin/devices/{userId}', [MainController::class, 'updateDevice'])->name("admin.devices.update");
    Route::post('/admin/devices/{userId}/relay', [MainController::class, 'toggleRelay'])->name("admin.devices.relay");
    Route::post('/admin/devices/{userId}/topup', [MainController::class, 'manualTopup'])->name("admin.devices.topup");
    Route::post('/admin/payments/{userId}/confirm', [MainController::class, 'forcePaymentConfirm'])->name("admin.payments.confirm");
    Route::post('/admin/customers/{userId}/toggle', [MainController::class, 'toggleCustomerStatus'])->name("admin.customers.toggle");
    Route::get('/admin/export/payments', [MainController::class, 'adminExportPayments'])->name("admin.export.payments");
    Route::get('/admin/export/customers', [MainController::class, 'adminExportCustomers'])->name("admin.export.customers");
    Route::get('/admin/export/usage', [MainController::class, 'adminExportUsage'])->name("admin.export.usage");

    Route::get('/usage', [MainController::class, 'usage'])->name("usage");
    Route::get('/usage/latest', [MainController::class, 'usageLatest'])->name("usage.latest");
    Route::get('/usage/history', [MainController::class, 'usageHistory'])->name("usage.history");
    Route::get('/usage/export', [MainController::class, 'downloadUsageReport'])->name("usage.export");

    Route::get('/bills', [MainController::class, 'bill'])->name("bills");
    Route::get('/bills/daily', [MainController::class, 'billingDaily'])->name("bills.daily");
    Route::get('/bills/daily/export', [MainController::class, 'downloadDailyBillingReport'])->name("bills.daily.export");
    Route::get('/invoices', [MainController::class, 'invoices'])->name("invoices");
    Route::get('/invoices/{transactionId}', [MainController::class, 'invoiceShow'])->name("invoices.show");
    Route::get('/reports/invoices', [MainController::class, 'downloadInvoicesReport'])->name('reports.invoices');
    Route::get('/payments', [MainController::class, 'payments'])->name("payments");
    Route::get('/reports/bills', [MainController::class, 'downloadBillsReport'])->name('reports.bills');

    Route::get('/profile', function () {
        return view('profile');
    })->name("profile");

    Route::post('/payment', [OperationController::class, 'payment'])->name('payment');
    Route::get('/payment/confirm', [OperationController::class, 'paymentConfirm'])->name('run.task');
    Route::get('/test', [OperationController::class, 'testPayment']);
    Route::get('/meter-unit', function() {
        $bills = DB::table('meter_status')->where('user_id', Auth::user()->id)->first();
        return response()->json(['unit' => $bills?->unit ?? 0]);
    });
    
});

Route::get('/login', function () {
    return view('login');
})->name("login");

Route::get('/register', function () {
    return view('registration');
})->name("register");

Route::post('/register/send-otp', [AuthController::class, 'sendRegistrationOtp'])->name('register.send-otp');
Route::post('/register/verify-otp', [AuthController::class, 'verifyRegistrationOtp'])->name('register.verify-otp');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');

Route::post('/login/operation', [AuthController::class, 'authenticate'])->name('Login.operation');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/balance', [OperationController::class, 'checkBalance']);
Route::get('/operation/{device}', [OperationController::class, 'operation']);
