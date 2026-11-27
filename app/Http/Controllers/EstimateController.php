<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateGenerationRequest;
use App\ImageProvider\ImageProviderClient;
use App\Pricing\PriceCalculator;
use Illuminate\Http\JsonResponse;

/**
 * The price shown before submitting. Computed on the server with the same code
 * that records it, so the page never holds a second copy of the pricing rules.
 */
class EstimateController extends Controller
{
    public function __invoke(
        EstimateGenerationRequest $request,
        ImageProviderClient $provider,
        PriceCalculator $calculator,
    ): JsonResponse {
        return response()->json(
            $calculator->estimate($request->spec(), $provider->pricing())->toArray(),
        );
    }
}
