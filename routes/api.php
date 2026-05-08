<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Employees\EmployeesController;
use App\Http\Controllers\Estimates\EstimatesController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Invoices\InvoicesController;
use App\Http\Controllers\Jobs\JobsController;
use App\Http\Controllers\Kpis\KpisController;
use App\Http\Controllers\Maps\MapsController;
use App\Http\Controllers\Mentions\MentionsController;
use App\Http\Controllers\Others\OthersController;
use App\Http\Controllers\PriceBook\PriceBookController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Schedules\SchedulesController;
use App\Http\Controllers\Templates\TemplatesController;
use App\Http\Controllers\Wages\WagesDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (versioned: /api/v1/...)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->controller(LoginController::class)->group(function () {
        Route::post('sign-in', 'signIn')->middleware('throttle:auth')->name('auth.sign-in');
        Route::post('refresh', 'refresh')->middleware('throttle:auth')->name('auth.refresh');
        Route::middleware('jwt')->group(function () {
            Route::get('me', 'me')->name('auth.me');
            Route::post('logout', 'logout')->name('auth.logout');
        });
    });

    Route::middleware('jwt')->group(function () {

        // Employees
        Route::prefix('employees')->controller(EmployeesController::class)->group(function () {
            Route::get('/', 'index')->name('employees.index');
            Route::post('/', 'createEmployee')->name('employees.create');
            Route::post('upload-pdf', 'uploadPdf')->middleware('throttle:uploads')->name('employees.upload-pdf');
            Route::get('permits-dashboard', 'permitsDashboard')->name('employees.permits-dashboard');
            Route::post('permissions/employee', 'updateEmployeePermission')->name('employees.update-permission');
            Route::post('permissions/permit', 'updatePermitPermission')->name('employees.update-permit-permission');
            Route::post('permissions/reset', 'resetEmployeeToTypeDefault')->name('employees.reset-permissions');
        });

        // Sales
        Route::prefix('sales')->controller(SalesDashboardController::class)->group(function () {
            Route::get('dashboard', 'index')->name('sales.dashboard');
            Route::get('dashboard/export', 'export')->name('sales.dashboard.export');
            Route::post('dashboard/assign-setter', 'assignSetter')->name('sales.dashboard.assign-setter');
            Route::post('dashboard/bulk-action', 'bulkAction')->name('sales.dashboard.bulk-action');
        });

        // Wages
        Route::prefix('wages')->controller(WagesDashboardController::class)->group(function () {
            Route::get('dashboard', 'dashboard')->name('wages.dashboard');
            Route::post('filter', 'getFilter')->name('wages.filter');
            Route::post('wages-job', 'getJob')->name('wages.wages-job');
            Route::post('existing-job', 'getExistingJob')->name('wages.existing-job');
            Route::post('create-job', 'createJob')->name('wages.create-job');
            Route::post('update-job', 'updateJob')->name('wages.update-job');
            Route::post('update-record', 'updateRecord')->name('wages.update-record');
            Route::delete('job/{id}', 'deleteJob')->name('wages.delete-job');
            Route::get('export', 'export')->name('wages.export');
        });

        // Schedules
        Route::prefix('schedules')->controller(SchedulesController::class)->group(function () {
            Route::get('by-technician', 'byTechnician')->name('schedules.by-technician');
            Route::get('by-closer', 'byCloser')->name('schedules.by-closer');
            Route::get('all-technicians', 'allTechnicians')->name('schedules.all-technicians');
            Route::get('all-closers', 'allClosers')->name('schedules.all-closers');
            Route::get('modifications', 'modifications')->name('schedules.modifications');
        });

        // KPIs
        Route::prefix('kpis')->controller(KpisController::class)->group(function () {
            Route::get('setters', 'setters')->name('kpis.setters');
            Route::get('closers', 'closers')->name('kpis.closers');
            Route::get('users-activity', 'usersActivity')->name('kpis.users-activity');
        });

        // Maps
        Route::prefix('maps')->controller(MapsController::class)->group(function () {
            Route::get('os-estimates', 'osEstimates')->name('maps.os-estimates');
        });

        // Estimates
        Route::prefix('estimates')->controller(EstimatesController::class)->group(function () {
            Route::get('/', 'index')->name('estimates.index');
            Route::post('/', 'store')->name('estimates.store');
            Route::get('{id}', 'show')->name('estimates.show')->where('id', '[a-f0-9]{24}');
            Route::patch('{id}', 'update')->name('estimates.update')->where('id', '[a-f0-9]{24}');
            Route::delete('{id}', 'destroy')->name('estimates.destroy')->where('id', '[a-f0-9]{24}');
            Route::post('{id}/email', 'sendEmail')->name('estimates.email')->where('id', '[a-f0-9]{24}');
            Route::post('{id}/customer-result', 'customerResult')->name('estimates.customer-result')->where('id', '[a-f0-9]{24}');
        });

        // Jobs
        Route::prefix('jobs')->controller(JobsController::class)->group(function () {
            Route::get('dashboard', 'dashboard')->name('jobs.dashboard');
            Route::get('layout', 'layout')->name('jobs.layout');
            Route::patch('layout', 'updateLayout')->name('jobs.update-layout');
        });

        // Invoices
        Route::prefix('invoices')->controller(InvoicesController::class)->group(function () {
            Route::get('/', 'index')->name('invoices.index');
            Route::post('/', 'store')->name('invoices.store');
            Route::get('{id}', 'show')->name('invoices.show')->where('id', '[a-f0-9]{24}');
            Route::patch('{id}', 'update')->name('invoices.update')->where('id', '[a-f0-9]{24}');
            Route::delete('{id}', 'destroy')->name('invoices.destroy')->where('id', '[a-f0-9]{24}');
            Route::get('{id}/pdf', 'pdf')->name('invoices.pdf')->where('id', '[a-f0-9]{24}');
            Route::post('{id}/email', 'email')->name('invoices.email')->where('id', '[a-f0-9]{24}');
        });

        // Mentions
        Route::prefix('mentions')->controller(MentionsController::class)->group(function () {
            Route::get('received', 'received')->name('mentions.received');
            Route::get('sent', 'sent')->name('mentions.sent');
            Route::post('{id}/read', 'markRead')->name('mentions.read')->where('id', '[a-f0-9]{24}');
        });

        // Price Book
        Route::prefix('price-book')->controller(PriceBookController::class)->group(function () {
            Route::get('/', 'index')->name('price-book.index');
            Route::get('categories', 'categories')->name('price-book.categories');
            Route::post('/', 'store')->name('price-book.store');
            Route::patch('{id}', 'update')->name('price-book.update')->where('id', '[a-f0-9]{24}');
            Route::delete('{id}', 'destroy')->name('price-book.destroy')->where('id', '[a-f0-9]{24}');
            Route::post('import', 'import')->middleware('throttle:uploads')->name('price-book.import');
            Route::get('export', 'export')->name('price-book.export');
        });

        // Templates
        Route::prefix('templates')->controller(TemplatesController::class)->group(function () {
            Route::get('{kind}', 'show')->name('templates.show');
            Route::patch('{kind}', 'update')->name('templates.update');
        });

        // Others
        Route::prefix('others')->controller(OthersController::class)->group(function () {
            Route::get('webhooks', 'webhooks')->name('others.webhooks');
            Route::get('apis', 'apis')->name('others.apis');
            Route::get('company-profile', 'companyProfile')->name('others.company-profile');
            Route::patch('company-profile', 'updateCompanyProfile')->name('others.update-company-profile');
            Route::get('cron-jobs', 'cronJobs')->name('others.cron-jobs');
            Route::get('database-details', 'databaseDetails')->middleware('role:Admin')->name('others.database-details');
            Route::post('justcall/proxy', 'justCallProxy')->name('others.justcall-proxy');
        });

        // Admin (role-restricted)
        Route::prefix('admin')->controller(AdminController::class)->middleware('role:Admin')->group(function () {
            Route::get('users', 'listUsers')->name('admin.users.list');
            Route::post('users/reset-password', 'resetPassword')->name('admin.users.reset-password');
            Route::get('analytics/login', 'loginAnalytics')->name('admin.analytics.login');
        });
    });
});
