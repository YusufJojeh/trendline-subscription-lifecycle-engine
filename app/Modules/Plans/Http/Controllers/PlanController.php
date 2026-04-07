<?php

namespace App\Modules\Plans\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Plans\Http\Requests\StorePlanRequest;
use App\Modules\Plans\Http\Requests\UpdatePlanRequest;
use App\Modules\Plans\Http\Resources\PlanResource;
use App\Modules\Plans\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(
            Plan::query()->with('prices')->latest()->get()
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::query()->create($request->validated());

        return (new PlanResource($plan->load('prices')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Plan $plan): PlanResource
    {
        return new PlanResource($plan->load('prices'));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $plan->fill($request->validated())->save();

        return new PlanResource($plan->load('prices'));
    }
}
