<?php

namespace App\UseCases\Stats;

use App\Repositories\UserExercise\UserExerciseRepositoryInterface;
use App\Repositories\Exercise\ExerciseRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FindUserStatsUseCase
{
    private string $startDate;

    public function __construct(
        private UserExerciseRepositoryInterface $userExerciseRepository,
        private ExerciseRepositoryInterface $exerciseRepository
    ) {}

    public function execute(string $startDate, string $endDate, ?string $period = null): array
    {
        $this->startDate = $startDate;
        $user = Auth::user();
        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => ['Utilisateur non authentifié']
            ]);
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Récupération de tous les exercices sur la période pour le watch_time
        $allExercises = $this->userExerciseRepository->findByPeriod(
            $user->id,
            $start,
            $end
        );

        // Récupération des exercices complétés uniquement pour XP et comptage
        $completedExercises = $this->userExerciseRepository->findCompletedByPeriod(
            $user->id,
            $start,
            $end
        );

        // Calcul des statistiques
        $stats = [
            'videos_completed' => count($completedExercises),
            'total_xp' => 0,
            'total_training_time' => 0,
            'most_trained_exercise' => null
        ];

        // Comptage des exercices pour trouver le plus pratiqué
        $exerciseCount = [];
        $dailyXp = [];

        foreach ($completedExercises as $completed) {
            $exercise = $this->exerciseRepository->findById($completed['exercise_id']);
            
            // Utiliser updated_at si completed_at est NULL (exercice complété via marge d'erreur)
            $completedAt = $completed['completed_at'] 
                ? Carbon::parse($completed['completed_at'])
                : Carbon::parse($completed['updated_at'] ?? $completed['created_at'] ?? now());
            
            // Cumul des totaux
            $stats['total_xp'] += $exercise->getXpValue();
            $stats['total_training_time'] += $completed['watch_time'];

            // Comptage pour l'exercice le plus pratiqué
            $exerciseCount[$completed['exercise_id']] = ($exerciseCount[$completed['exercise_id']] ?? 0) + 1;

            // Cumul XP par jour
            $dayKey = $completedAt->format('Y-m-d');
            $dailyXp[$dayKey] = ($dailyXp[$dayKey] ?? 0) + $exercise->getXpValue();
        }

        // Calcul du temps total d'entraînement et comptage par exercice
        $exerciseWatchTime = [];
        foreach ($allExercises as $exercise) {
            $exerciseWatchTime[$exercise['exercise_id']] = 
                ($exerciseWatchTime[$exercise['exercise_id']] ?? 0) + $exercise['watch_time'];
        }

        // Détermination de l'exercice le plus pratiqué (basé sur watch_time)
        if (!empty($exerciseWatchTime)) {
            $mostTrainedId = array_search(max($exerciseWatchTime), $exerciseWatchTime);
            $mostTrainedExercise = $this->exerciseRepository->findById($mostTrainedId);
            $stats['most_trained_exercise'] = [
                'id' => $mostTrainedId,
                'name' => $mostTrainedExercise->getTitle(),
                'watch_time' => $exerciseWatchTime[$mostTrainedId]
            ];
        }

        // Ajout des XP journaliers selon la période
        if ($period !== 'month') {
            $stats['overall_xp'] = $this->formatOverallXp($dailyXp, $period === 'day');
        }

        return $stats;
    }

    private function formatOverallXp(array $dailyXp, bool $singleDay): array
    {
        if ($singleDay) {
            // Pour period=day, on retourne uniquement le jour demandé
            return array_map(function ($xp, $date) {
                return [
                    'day' => Carbon::parse($date)->format('D'),
                    'xp' => $xp
                ];
            }, $dailyXp, array_keys($dailyXp));
        }

        // Pour period=week, on utilise startDate comme point de départ
        $formattedXp = [];
        $startDate = Carbon::parse($this->startDate);
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $formattedXp[] = [
                'day' => $startDate->copy()->addDays($i)->format('D'),
                'xp' => $dailyXp[$date] ?? 0
            ];
        }

        return $formattedXp;
    }
} 