<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::view('/', 'pages.dashboard', ['pageTitle' => 'Dashboard'])->name('dashboard');

    Route::view('/emails', 'pages.emails', ['pageTitle' => 'Daftar Email'])->name('emails.index');

    Route::view('/simulasi', 'pages.campaigns.index', ['pageTitle' => 'Simulasi'])->name('campaigns.index');
    Route::view('/simulasi/buat', 'pages.campaigns.create', ['pageTitle' => 'Buat Simulasi'])->name('campaigns.create');
    Route::view('/simulasi/detail', 'pages.campaigns.show', ['pageTitle' => 'Detail Simulasi'])->name('campaigns.show');

    Route::view('/email-templates', 'pages.templates.index', ['pageTitle' => 'Template Email'])->name('email-templates.index');

    Route::view('/reports', 'pages.reports', ['pageTitle' => 'Laporan'])->name('reports.index');

    Route::view('/settings', 'pages.settings', ['pageTitle' => 'Pengaturan'])->name('settings.index');
    Route::resource('admins', AdminController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
