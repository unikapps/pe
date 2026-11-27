<?php

use App\Http\Controllers\EstimateController;
use App\Http\Controllers\GenerationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generations')->name('home');

Route::middleware('auth')->group(function () {
    // Old links (welcome page, header, passkey login) still point here.
    Route::redirect('dashboard', '/generations')->name('dashboard');

    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::post('generations/estimate', EstimateController::class)->name('generations.estimate');
    Route::get('generations/{generation}/image', [GenerationController::class, 'image'])->name('generations.image');
});

require __DIR__.'/settings.php';
