<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Lifecycle\Services\AccessResolver;
use App\Modules\Subscriptions\Models\Subscription;
use Illuminate\Http\JsonResponse;

class SubscriptionAccessController extends Controller
{
    public function __construct(
        private readonly AccessResolver $accessResolver,
    ) {
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return response()->json([
            'data' => [
                'subscription_id' => $subscription->id,
                ...$this->accessResolver->explain($subscription),
            ],
        ]);
    }
}
