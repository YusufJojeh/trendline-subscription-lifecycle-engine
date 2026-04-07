<?php

use App\Modules\Payments\Http\Controllers\PaymentAttemptController;
use App\Modules\Plans\Http\Controllers\PlanController;
use App\Modules\Plans\Http\Controllers\PlanPriceController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionAccessController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::apiResource('plans', PlanController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('plans/{plan}/prices', [PlanPriceController::class, 'index']);
    Route::post('plans/{plan}/prices', [PlanPriceController::class, 'store']);
    Route::patch('plans/{plan}/prices/{price}', [PlanPriceController::class, 'update']);

    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscriptions', [SubscriptionController::class, 'store']);
    Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('subscriptions/{subscription}/access', [SubscriptionAccessController::class, 'show']);

    Route::get('subscriptions/{subscription}/payment-attempts', [PaymentAttemptController::class, 'index']);
    Route::post('payments/success', [PaymentAttemptController::class, 'recordSuccessful']);
    Route::post('payments/failure', [PaymentAttemptController::class, 'recordFailed']);
});
