<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Actions\RecordPaymentAttemptAction;
use App\Modules\Payments\DTOs\RecordPaymentData;
use App\Modules\Payments\Http\Requests\RecordFailedPaymentRequest;
use App\Modules\Payments\Http\Requests\RecordSuccessfulPaymentRequest;
use App\Modules\Payments\Http\Resources\PaymentAttemptResource;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Subscriptions\Models\Subscription;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentAttemptController extends Controller
{
    public function __construct(
        private readonly RecordPaymentAttemptAction $recordPaymentAttemptAction,
    ) {
    }

    public function index(Subscription $subscription): AnonymousResourceCollection
    {
        return PaymentAttemptResource::collection(
            PaymentAttempt::query()
                ->where('subscription_id', $subscription->id)
                ->with(['subscription.plan', 'subscription.planPrice'])
                ->latest('attempted_at')
                ->get()
        );
    }

    public function recordSuccessful(RecordSuccessfulPaymentRequest $request): PaymentAttemptResource
    {
        $paymentAttempt = $this->recordPaymentAttemptAction->recordSuccessful(
            RecordPaymentData::fromArray($request->validated())
        );

        return new PaymentAttemptResource($paymentAttempt);
    }

    public function recordFailed(RecordFailedPaymentRequest $request): PaymentAttemptResource
    {
        $paymentAttempt = $this->recordPaymentAttemptAction->recordFailed(
            RecordPaymentData::fromArray($request->validated())
        );

        return new PaymentAttemptResource($paymentAttempt);
    }
}
