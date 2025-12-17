<?php

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\UseCases\Stats\FindDashboardOverviewUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindDashboardOverviewAction extends Controller
{
    public function __construct(
        private FindDashboardOverviewUseCase $useCase
    ) {}

    /**
     * Récupère les KPIs et données du dashboard overview
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $result = $this->useCase->execute($startDate, $endDate);
            
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la récupération des données du dashboard',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

