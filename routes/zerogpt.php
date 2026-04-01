<?php

use hexa_package_zerogpt\Http\Controllers\ZeroGptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('zerogpt/settings', [ZeroGptController::class, 'settings'])->name('zerogpt.settings');
    Route::post('zerogpt/settings', [ZeroGptController::class, 'saveSettings'])->name('zerogpt.settings.save');
    Route::post('zerogpt/test', [ZeroGptController::class, 'testConnection'])->name('zerogpt.test');
    Route::get('raw-zerogpt', [ZeroGptController::class, 'raw'])->name('zerogpt.raw');
    Route::post('zerogpt/detect', [ZeroGptController::class, 'detect'])->name('zerogpt.detect');
});
