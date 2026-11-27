<?php

use App\Http\Controllers\ProviderWebhookController;
use Illuminate\Support\Facades\Route;

// Outside the web middleware group: no session, no CSRF token (the provider has none).
// Requests are authenticated by their signature instead.
Route::post('webhooks/provider', ProviderWebhookController::class)->name('webhooks.provider');
