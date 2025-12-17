<?php

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\UseCases\Stats\FindDashboardOverviewUseCase;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindDashboardOverviewAction extends Controller
{
    public function __construct(
        private FindDashboardOverviewUseCase $useCase,
        private TelegramService $telegramService
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

            // Envoyer une notification Telegram (erreurs silencieusement ignorées)
            try {
                $user = $request->user();
                $userName = $user ? ($user->full_name ?? $user->email ?? 'Utilisateur inconnu') : 'Utilisateur inconnu';
                $message = "📊 <b>Dashboard Overview consulté</b>\n\n";
                $message .= "👤 Utilisateur: {$userName}\n";
                $message .= "📅 Période: " . ($result['period']['start'] ?? 'N/A') . " - " . ($result['period']['end'] ?? 'N/A') . "\n";
                $message .= "🕐 Date: " . now()->format('Y-m-d H:i:s');

                $this->telegramService->sendMessage($message);
            } catch (\Exception $e) {
                // Erreur silencieusement ignorée - on continue comme si rien ne s'était passé
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la récupération des données du dashboard',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}


