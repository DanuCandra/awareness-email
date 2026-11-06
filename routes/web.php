<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\RecipientController;
use App\Http\Controllers\RecipientImportController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\SimulationTrackingController;
use App\Http\Controllers\SmtpTestController;
use App\Http\Controllers\TemplateCategoryController;
use App\Http\Controllers\WorkspaceSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/t/{token}', [SimulationTrackingController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:simulation-links')->name('simulation-links.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::view('/', 'pages.dashboard', ['pageTitle' => 'Dashboard'])->name('dashboard');

    Route::post('/emails/import/preview', [RecipientImportController::class, 'store'])->middleware('throttle:20,1')->name('emails.import.preview');
    Route::post('/emails/import/commit', [RecipientImportController::class, 'commit'])->middleware('throttle:20,1')->name('emails.import.commit');
    Route::delete('/emails/bulk', [RecipientController::class, 'bulkDestroy'])->name('emails.bulk-destroy');
    Route::resource('emails', RecipientController::class)->parameters(['emails' => 'recipient'])->only(['index', 'store', 'update', 'destroy']);

    Route::view('/simulasi/buat', 'pages.campaigns.create', ['pageTitle' => 'Buat Simulasi'])->name('campaigns.create');
    Route::view('/simulasi/detail', 'pages.campaigns.show', ['pageTitle' => 'Detail Simulasi'])->name('campaigns.show');
    Route::post('/simulasi/{simulation}/duplicate', [SimulationController::class, 'duplicate'])->name('campaigns.duplicate');
    Route::post('/simulasi/{simulation}/cancel', [SimulationController::class, 'cancel'])->name('campaigns.cancel');
    Route::resource('simulasi', SimulationController::class)->parameters(['simulasi' => 'simulation'])->only(['index', 'store', 'show', 'update', 'destroy'])->names(['index' => 'campaigns.index', 'store' => 'campaigns.store', 'show' => 'campaigns.data', 'update' => 'campaigns.update', 'destroy' => 'campaigns.destroy']);

    Route::resource('email-templates', EmailTemplateController::class)->parameters(['email-templates' => 'emailTemplate'])->only(['index', 'store', 'update', 'destroy']);
    Route::resource('template-categories', TemplateCategoryController::class)->parameters(['template-categories' => 'category'])->only(['store', 'update', 'destroy']);

    Route::view('/reports', 'pages.reports', ['pageTitle' => 'Laporan'])->name('reports.index');

    Route::get('/settings', [WorkspaceSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings/{section}', [WorkspaceSettingController::class, 'update'])
        ->whereIn('section', ['identity', 'smtp', 'landing', 'reports', 'audit'])->name('settings.update');
    Route::post('/settings/smtp/test-connection', [SmtpTestController::class, 'connection'])->middleware('throttle:5,1')->name('settings.smtp.connection');
    Route::post('/settings/smtp/test-email', [SmtpTestController::class, 'email'])->middleware('throttle:5,1')->name('settings.smtp.email');
    Route::resource('admins', AdminController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
