<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Employees\EmployeesController;
use App\Http\Controllers\Sales\SalesDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Login Routes
Route::controller(LoginController::class)->group(function () {
    Route::get('auth/me', 'Me')->name('me');
    Route::post('auth/sign-in', 'SignIn')->name('sign-in');
});

// Employees Routes
Route::controller(EmployeesController::class)->group(function () {
    Route::get('employees/get', 'getEmployees')->name('employees.get');
    Route::post('employees/create', 'createEmployee')->name('employees.create');
    Route::post('uploadPdf', 'uploadPdf')->name('employees.upload-pdf');
});

// Sales Routes
Route::controller(SalesDashboardController::class)->group(function () {
    Route::get('sales/dashboard', 'getDashboard')->name('sales.dashboard');
    Route::get('sales/dashboard/export', 'exportDashboard')->name('sales.dashboard.export');
    Route::post('sales/dashboard/assign-setter', 'assignSetter')->name('sales.dashboard.assign-setter');
    Route::post('sales/dashboard/bulk-action', 'bulkAction')->name('sales.dashboard.bulk-action');
});