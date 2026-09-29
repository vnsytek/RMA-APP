<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RmaTicketController;
use App\Http\Controllers\ServiceCenterController;
use App\Http\Controllers\TicketActionController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketErpController;
use App\Http\Controllers\TicketQuoteItemController;
use App\Http\Controllers\TicketReplacedPartController;
use App\Http\Controllers\TicketSequenceController;
use App\Http\Controllers\TicketSlipController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::redirect('/', '/tickets');

    Route::resource('tickets', RmaTicketController::class)->only(['index', 'create', 'store', 'show', 'update']);

    Route::prefix('tickets/{ticket}')->name('tickets.')->scopeBindings()->group(function () {
        Route::post('actions/{action}', [TicketActionController::class, 'store'])->name('actions.store');
        Route::post('quote-items', [TicketQuoteItemController::class, 'store'])->name('quote-items.store');
        Route::delete('quote-items/{quoteItem}', [TicketQuoteItemController::class, 'destroy'])->name('quote-items.destroy');
        Route::post('replaced-parts', [TicketReplacedPartController::class, 'store'])->name('replaced-parts.store');
        Route::delete('replaced-parts/{replacedPart}', [TicketReplacedPartController::class, 'destroy'])->name('replaced-parts.destroy');
        Route::put('erp', [TicketErpController::class, 'update'])->name('erp.update');
        Route::post('attachments', [TicketAttachmentController::class, 'store'])->name('attachments.store');
        Route::get('attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('attachments.show');
        Route::delete('attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::get('slips/{type}', [TicketSlipController::class, 'show'])->whereIn('type', ['receipt', 'return'])->name('slips.show');
        Route::get('slips/{type}/excel', [TicketSlipController::class, 'excel'])->whereIn('type', ['receipt', 'return'])->name('slips.excel');
    });

    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::resource('customers', CustomerController::class)->only(['index', 'store', 'edit', 'update']);
    Route::resource('service-centers', ServiceCenterController::class)->only(['index', 'store', 'update']);

    Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('catalog/device-types', [CatalogController::class, 'storeDeviceType'])->name('catalog.device-types.store');
    Route::post('catalog/brands', [CatalogController::class, 'storeBrand'])->name('catalog.brands.store');
    Route::post('catalog/product-models', [CatalogController::class, 'storeProductModel'])->name('catalog.product-models.store');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::view('flows', 'flows')->name('flows');
    Route::get('sequences', [TicketSequenceController::class, 'index'])->name('sequences.index');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('lookup/models', [LookupController::class, 'models'])->name('lookup.models');
    Route::get('lookup/devices', [LookupController::class, 'device'])->name('lookup.devices');

    Route::middleware('can:admin')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::patch('service-centers/{serviceCenter}/toggle', [ServiceCenterController::class, 'toggle'])->name('service-centers.toggle');
        Route::put('catalog/device-types/{deviceType}', [CatalogController::class, 'updateDeviceType'])->name('catalog.device-types.update');
        Route::patch('catalog/device-types/{deviceType}/toggle', [CatalogController::class, 'toggleDeviceType'])->name('catalog.device-types.toggle');
        Route::patch('catalog/brands/{brand}/toggle', [CatalogController::class, 'toggleBrand'])->name('catalog.brands.toggle');
        Route::patch('catalog/product-models/{productModel}/toggle', [CatalogController::class, 'toggleProductModel'])->name('catalog.product-models.toggle');
    });
});
