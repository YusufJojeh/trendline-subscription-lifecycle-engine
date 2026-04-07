<?php

namespace App\Modules\Plans\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Plans\Http\Requests\StorePlanPriceRequest;
use App\Modules\Plans\Http\Requests\UpdatePlanPriceRequest;
use App\Modules\Plans\Http\Resources\PlanPriceResource;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanPriceController extends Controller
{
    public function index(Plan $plan): AnonymousResourceCollection
    {
        return PlanPriceResource::collection(
            $plan->prices()->latest()->get()
        );
    }

    public function store(StorePlanPriceRequest $request, Plan $plan): JsonResponse
    {
        $price = $plan->prices()->create($request->validated());

        return (new PlanPriceResource($price))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdatePlanPriceRequest $request, Plan $plan, PlanPrice $price): PlanPriceResource
    {
        abort_unless($price->plan_id === $plan->id, 404);

        $price->fill($request->validated())->save();

        return new PlanPriceResource($price);
    }
}
