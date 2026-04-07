<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscriptions\Actions\CancelSubscriptionAction;
use App\Modules\Subscriptions\Actions\StartSubscriptionAction;
use App\Modules\Subscriptions\DTOs\StartSubscriptionData;
use App\Modules\Subscriptions\Http\Requests\StoreSubscriptionRequest;
use App\Modules\Subscriptions\Http\Resources\SubscriptionResource;
use App\Modules\Subscriptions\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly StartSubscriptionAction $startSubscriptionAction,
        private readonly CancelSubscriptionAction $cancelSubscriptionAction,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Subscription::query()->with(['plan', 'planPrice'])->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return SubscriptionResource::collection($query->get());
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->startSubscriptionAction->execute(
            StartSubscriptionData::fromArray($request->validated())
        );

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription->load(['plan', 'planPrice']));
    }

    public function cancel(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource(
            $this->cancelSubscriptionAction->execute($subscription->id)
        );
    }
}
