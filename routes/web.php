<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SimulatorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/config', [AdminController::class, 'config'])->name('config');
    Route::post('/config', [AdminController::class, 'configUpdate'])->name('config.update');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/reports/export/csv', [AdminController::class, 'reportsExportCsv'])->name('reports.export.csv');
    Route::get('/reports/export/pdf', [AdminController::class, 'reportsExportPdf'])->name('reports.export.pdf');
    Route::get('/simulator', [AdminController::class, 'simulator'])->name('simulator');
    Route::prefix('simulate')->name('simulate.')->group(function () {
        Route::get('/', [SimulatorController::class, 'index'])->name('index');
        Route::post('/', [SimulatorController::class, 'simulate'])->name('post');
        Route::get('/flow/{flowId}', [SimulatorController::class, 'flowSchema'])->name('flow');
    });
    Route::get('/help', [AdminController::class, 'help'])->name('help');

    Route::get('/agents', [AdminController::class, 'agents'])->name('agents');
    Route::get('/agents/{agent}', [AdminController::class, 'agentDetail'])->name('agents.detail');
    Route::post('/agents/{agent}/toggle-block', [AdminController::class, 'agentToggleBlock'])->name('agents.toggle-block');

    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/users/{user}/edit', [AdminController::class, 'userEdit'])->name('users.edit');
    Route::post('/users/{user}/update', [AdminController::class, 'userUpdate'])->name('users.update');
    Route::post('/users/{user}/password', [AdminController::class, 'userPassword'])->name('users.password');
    Route::post('/users/{user}/toggle-block', [AdminController::class, 'userToggleBlock'])->name('users.toggle-block');

    Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
    Route::get('/customers/{customer}', [AdminController::class, 'customerDetail'])->name('customers.detail');
});
