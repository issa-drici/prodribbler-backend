<?php

namespace App\UseCases\Stats;

use App\Repositories\UserExercise\UserExerciseRepositoryInterface;
use App\Repositories\Exercise\ExerciseRepositoryInterface;
use App\Repositories\UserProfile\UserProfileRepositoryInterface;
use App\Repositories\User\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FindDashboardOverviewUseCase
{
    // Marge d'erreur pour considérer un exercice comme complété (en secondes)
    private const COMPLETION_TOLERANCE_SECONDS = 4;

    public function __construct(
        private UserExerciseRepositoryInterface $userExerciseRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
        private UserProfileRepositoryInterface $userProfileRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Retourne la condition SQL pour vérifier si un exercice est complété avec la marge d'erreur
     */
    private function getCompletedCondition(): string
    {
        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
        return "ue.completed_at IS NOT NULL OR (ue.watch_time >= e.duration - {$tolerance})";
    }

    public function execute(?string $startDate = null, ?string $endDate = null): array
    {
        // Définir la période (par défaut: mois précédent)
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subMonth()->startOfDay();

        // Période précédente pour calculer les changements
        $previousPeriodStart = $start->copy()->subMonth();
        $previousPeriodEnd = $start->copy()->subDay()->endOfDay();

        // Calculer les KPIs
        $kpi = $this->calculateKPIs($start, $end, $previousPeriodStart, $previousPeriodEnd);

        // Calculer les graphiques
        $charts = $this->calculateCharts($start, $end);

        // Calculer les cohorts de rétention
        $retentionCohorts = $this->userExerciseRepository->getRetentionCohorts($start, $end);

        // Performance du contenu
        $contentPerformance = $this->calculateContentPerformance($start, $end);

        // Segments d'utilisateurs - passer la période pour filtrer correctement
        $userSegments = $this->calculateUserSegments($start, $end);

        return [
            'period' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d')
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'retention_cohorts' => $retentionCohorts,
            'content_performance' => $contentPerformance,
            'user_segments' => $userSegments
        ];
    }

    private function calculateKPIs(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        // MAU
        $mau = $this->userExerciseRepository->countActiveUsersByPeriod($start, $end);
        $prevMau = $this->userExerciseRepository->countActiveUsersByPeriod($prevStart, $prevEnd);
        $mauChange = $prevMau > 0 ? round((($mau - $prevMau) / $prevMau) * 100, 1) : 0;

        // Stickiness (DAU/WAU)
        $dau = $this->userExerciseRepository->countDailyActiveUsers($end->copy()->subDay());
        $wau = $this->userExerciseRepository->countWeeklyActiveUsers($end->copy()->subWeek(), $end);
        $stickiness = $wau > 0 ? round(($dau / $wau) * 100, 1) : 0;
        
        $prevDau = $this->userExerciseRepository->countDailyActiveUsers($prevEnd->copy()->subDay());
        $prevWau = $this->userExerciseRepository->countWeeklyActiveUsers($prevEnd->copy()->subWeek(), $prevEnd);
        $prevStickiness = $prevWau > 0 ? round(($prevDau / $prevWau) * 100, 1) : 0;
        $stickinessChange = $prevStickiness > 0 ? round($stickiness - $prevStickiness, 1) : 0;

        // Resurrection Rate
        $resurrection = $this->userExerciseRepository->getResurrectionUsers($start, $end);
        // Compter les utilisateurs inactifs depuis plus de 30 jours au début de la période
        $cutoffDate = $start->copy()->subDays(30);
        $totalInactive = DB::table('user_exercises')
            ->select(DB::raw('COUNT(DISTINCT user_id) as count'))
            ->whereRaw('user_id IN (
                SELECT user_id FROM (
                    SELECT user_id, MAX(updated_at) as last_activity 
                    FROM user_exercises 
                    GROUP BY user_id
                ) as last_activities
                WHERE last_activity <= ?
            )', [$cutoffDate])
            ->first();
        $resurrectionRate = $totalInactive && $totalInactive->count > 0 
            ? round(($resurrection / $totalInactive->count) * 100, 1) 
            : 0;
        
        $prevResurrection = $this->userExerciseRepository->getResurrectionUsers($prevStart, $prevEnd);
        $prevCutoffDate = $prevStart->copy()->subDays(30);
        $prevTotalInactive = DB::table('user_exercises')
            ->select(DB::raw('COUNT(DISTINCT user_id) as count'))
            ->whereRaw('user_id IN (
                SELECT user_id FROM (
                    SELECT user_id, MAX(updated_at) as last_activity 
                    FROM user_exercises 
                    GROUP BY user_id
                ) as last_activities
                WHERE last_activity <= ?
            )', [$prevCutoffDate])
            ->first();
        $prevResurrectionRate = $prevTotalInactive && $prevTotalInactive->count > 0 
            ? round(($prevResurrection / $prevTotalInactive->count) * 100, 1) 
            : 0;
        $resurrectionChange = round($resurrectionRate - $prevResurrectionRate, 1);

        // Average Sessions Per User
        $avgSessions = $this->userExerciseRepository->getAverageSessionsPerUser($start, $end);
        $prevAvgSessions = $this->userExerciseRepository->getAverageSessionsPerUser($prevStart, $prevEnd);
        $avgSessionsChange = round($avgSessions - $prevAvgSessions, 1);

        // Average Session Duration
        $avgDuration = $this->userExerciseRepository->getAverageSessionDuration($start, $end);
        $prevAvgDuration = $this->userExerciseRepository->getAverageSessionDuration($prevStart, $prevEnd);
        $avgDurationChange = round($avgDuration - $prevAvgDuration);

        // Retention D1
        $retentionD1 = $this->calculateRetentionD1($start, $end);
        $prevRetentionD1 = $this->calculateRetentionD1($prevStart, $prevEnd);
        $retentionD1Change = round($retentionD1 - $prevRetentionD1, 1);

        // Completion Rate
        $completionStats = $this->userExerciseRepository->getExerciseCompletionStats($start, $end);
        $completionRate = $completionStats['overall_completion_rate'] ?? 0;
        $prevCompletionStats = $this->userExerciseRepository->getExerciseCompletionStats($prevStart, $prevEnd);
        $prevCompletionRate = $prevCompletionStats['overall_completion_rate'] ?? 0;
        $completionChange = round($completionRate - $prevCompletionRate, 1);

        // Churn Risk Count - utiliser la date de fin de la période comme référence
        $churnRiskUsers = $this->userExerciseRepository->getChurnRiskUsers(14, $end);
        $churnRiskCount = count($churnRiskUsers);
        $prevChurnRiskUsers = $this->userExerciseRepository->getChurnRiskUsers(14, $prevEnd);
        $prevChurnRiskCount = count($prevChurnRiskUsers);
        $churnRiskChange = $churnRiskCount - $prevChurnRiskCount;

        return [
            'mau' => [
                'value' => $mau,
                'change' => $mauChange,
                'trend' => $mauChange >= 0 ? 'up' : 'down'
            ],
            'stickiness' => [
                'value' => $stickiness,
                'change' => $stickinessChange,
                'trend' => $stickinessChange >= 0 ? 'up' : 'down'
            ],
            'resurrection_rate' => [
                'value' => $resurrectionRate,
                'change' => $resurrectionChange,
                'trend' => $resurrectionChange >= 0 ? 'up' : 'down'
            ],
            'avg_sessions_per_user' => [
                'value' => round($avgSessions, 1),
                'change' => $avgSessionsChange,
                'trend' => $avgSessionsChange >= 0 ? 'up' : 'down'
            ],
            'avg_session_duration' => [
                'value' => (int) $avgDuration,
                'change' => $avgDurationChange,
                'trend' => $avgDurationChange >= 0 ? 'up' : 'down'
            ],
            'retention_d1' => [
                'value' => $retentionD1,
                'change' => $retentionD1Change,
                'trend' => $retentionD1Change >= 0 ? 'up' : 'down'
            ],
            'completion_rate' => [
                'value' => $completionRate,
                'change' => $completionChange,
                'trend' => $completionChange >= 0 ? 'up' : 'down'
            ],
            'churn_risk_count' => [
                'value' => $churnRiskCount,
                'change' => $churnRiskChange,
                'trend' => $churnRiskChange <= 0 ? 'good' : 'bad'
            ]
        ];
    }

    private function calculateRetentionD1(Carbon $start, Carbon $end): float
    {
        // Utilisateurs créés dans la période
        $newUsers = DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->pluck('id')
            ->toArray();

        if (empty($newUsers)) {
            return 0.0;
        }

        // Utilisateurs actifs le jour suivant leur inscription
        // Pour PostgreSQL, utiliser la syntaxe correcte
        $d1Active = DB::table('user_exercises')
            ->whereIn('user_id', $newUsers)
            ->join('users', 'user_exercises.user_id', '=', 'users.id')
            ->whereRaw("DATE(user_exercises.created_at) = (users.created_at::date + INTERVAL '1 day')::date")
            ->distinct('user_exercises.user_id')
            ->count('user_exercises.user_id');

        return count($newUsers) > 0 ? round(($d1Active / count($newUsers)) * 100, 1) : 0.0;
    }

    private function calculateCharts(Carbon $start, Carbon $end): array
    {
        $activityCurve = $this->userExerciseRepository->getActivityByDateRange($start, $end);
        $heatmap = $this->userExerciseRepository->getActivityByHourRange($start, $end);

        return [
            'activity_curve' => $activityCurve,
            'heatmap' => $heatmap
        ];
    }

    private function calculateContentPerformance(Carbon $start, Carbon $end): array
    {
        $popularExercises = $this->userExerciseRepository->getPopularExercises($start, $end, 10);
        $highDropoffExercises = $this->userExerciseRepository->getHighDropoffExercises($start, $end, 10);

        // Enrichir avec les détails des exercices
        $popularExercisesEnriched = [];
        foreach ($popularExercises as $exercise) {
            $exerciseEntity = $this->exerciseRepository->findById($exercise['id']);
            if ($exerciseEntity) {
                $level = null;
                if ($exerciseEntity->getLevelId()) {
                    // Récupérer le niveau si nécessaire
                }
                $popularExercisesEnriched[] = [
                    'id' => $exercise['id'],
                    'title' => $exerciseEntity->getTitle(),
                    'category' => 'Exercise', // À adapter selon votre structure
                    'views' => $exercise['views'],
                    'completion_rate' => $exercise['completion_rate']
                ];
            }
        }

        $highDropoffEnriched = [];
        foreach ($highDropoffExercises as $exercise) {
            $exerciseEntity = $this->exerciseRepository->findById($exercise['id']);
            if ($exerciseEntity) {
                $highDropoffEnriched[] = [
                    'id' => $exercise['id'],
                    'title' => $exerciseEntity->getTitle(),
                    'dropoff_rate' => $exercise['dropoff_rate'],
                    'avg_time_before_drop' => $exercise['avg_time_before_drop']
                ];
            }
        }

        return [
            'popular_exercises' => $popularExercisesEnriched,
            'high_dropoff_exercises' => $highDropoffEnriched
        ];
    }

    private function calculateUserSegments(Carbon $start, Carbon $end): array
    {
        // Churn Risk Users - utiliser la date de fin comme référence
        $churnRiskUsers = $this->userExerciseRepository->getChurnRiskUsers(14, $end);
        $churnRiskEnriched = [];
        foreach (array_slice($churnRiskUsers, 0, 10) as $userData) {
            $user = $this->userRepository->findById($userData['user_id']);
            $profile = $this->userProfileRepository->findByUserId($userData['user_id']);
            if ($user) {
                $churnRiskEnriched[] = [
                    'id' => $userData['user_id'],
                    'name' => $user['full_name'] ?? 'Unknown',
                    'days_inactive' => (int) round($userData['days_inactive'] ?? 0),
                    'total_xp' => $profile ? $profile->getTotalXp() : 0,
                    'plan' => 'Free' // À adapter si vous avez des plans
                ];
            }
        }

        // Power Users - Calculer l'XP directement depuis les exercices complétés
        // Utiliser une sous-requête pour calculer l'XP réel depuis les exercices complétés
        $powerUsersQuery = DB::table('users')
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->select(
                'users.id as user_id',
                'users.full_name',
                DB::raw('COALESCE(user_profiles.total_xp, 0) as total_xp_from_profile'),
                DB::raw("(SELECT COALESCE(SUM(e.xp_value), 0) 
                         FROM user_exercises ue 
                         JOIN exercises e ON e.id = ue.exercise_id 
                         WHERE ue.user_id = users.id 
                         AND ({$this->getCompletedCondition()})) as total_xp_calculated"),
                DB::raw("(SELECT COUNT(DISTINCT exercise_id) 
                         FROM user_exercises ue
                         JOIN exercises e ON e.id = ue.exercise_id
                         WHERE ue.user_id = users.id 
                         AND ({$this->getCompletedCondition()})) as completed_exercises"),
                DB::raw('(SELECT MAX(updated_at) 
                         FROM user_exercises 
                         WHERE user_id = users.id) as last_active')
            )
            ->whereExists(function($query) use ($start, $end) {
                $query->select(DB::raw(1))
                      ->from('user_exercises as ue_check')
                      ->whereColumn('ue_check.user_id', 'users.id')
                      ->whereBetween('ue_check.updated_at', [$start, $end]);
            })
            ->groupBy('users.id', 'users.full_name', 'user_profiles.total_xp')
            ->havingRaw("(COALESCE(user_profiles.total_xp, 0) > 0 OR (SELECT COUNT(DISTINCT exercise_id) FROM user_exercises ue JOIN exercises e ON e.id = ue.exercise_id WHERE ue.user_id = users.id AND ({$this->getCompletedCondition()})) > 0)")
            ->orderByRaw("GREATEST(COALESCE(user_profiles.total_xp, 0), (SELECT COALESCE(SUM(e.xp_value), 0) FROM user_exercises ue JOIN exercises e ON e.id = ue.exercise_id WHERE ue.user_id = users.id AND ({$this->getCompletedCondition()}))) DESC")
            ->orderByRaw("(SELECT COUNT(DISTINCT exercise_id) FROM user_exercises ue JOIN exercises e ON e.id = ue.exercise_id WHERE ue.user_id = users.id AND ({$this->getCompletedCondition()})) DESC")
            ->orderByRaw('(SELECT MAX(updated_at) FROM user_exercises WHERE user_id = users.id) DESC')
            ->limit(10)
            ->get();

        // Si aucun utilisateur actif dans la période, utiliser tous les utilisateurs avec XP ou activité
        if ($powerUsersQuery->isEmpty()) {
            $powerUsersQuery = DB::table('users')
                ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                ->select(
                    'users.id as user_id',
                    'users.full_name',
                    DB::raw('COALESCE(user_profiles.total_xp, 0) as total_xp_from_profile'),
                    DB::raw("(SELECT COALESCE(SUM(e.xp_value), 0) 
                             FROM user_exercises ue 
                             JOIN exercises e ON e.id = ue.exercise_id 
                             WHERE ue.user_id = users.id 
                             AND ({$this->getCompletedCondition()})) as total_xp_calculated"),
                    DB::raw("(SELECT COUNT(DISTINCT exercise_id) 
                             FROM user_exercises ue
                             JOIN exercises e ON e.id = ue.exercise_id
                             WHERE ue.user_id = users.id 
                             AND ({$this->getCompletedCondition()})) as completed_exercises"),
                    DB::raw('(SELECT MAX(updated_at) 
                             FROM user_exercises 
                             WHERE user_id = users.id) as last_active')
                )
                ->where(function($query) {
                    $query->where('user_profiles.total_xp', '>', 0)
                          ->orWhereExists(function($subQuery) {
                              $subQuery->select(DB::raw(1))
                                       ->from('user_exercises as ue_check')
                                       ->join('exercises as e_check', 'ue_check.exercise_id', '=', 'e_check.id')
                                       ->whereColumn('ue_check.user_id', 'users.id')
                                       ->whereRaw("({$this->getCompletedCondition()})");
                          });
                })
                ->groupBy('users.id', 'users.full_name', 'user_profiles.total_xp')
                ->orderByRaw("GREATEST(COALESCE(user_profiles.total_xp, 0), (SELECT COALESCE(SUM(e.xp_value), 0) FROM user_exercises ue JOIN exercises e ON e.id = ue.exercise_id WHERE ue.user_id = users.id AND ({$this->getCompletedCondition()}))) DESC")
                ->orderByRaw("(SELECT COUNT(DISTINCT exercise_id) FROM user_exercises ue JOIN exercises e ON e.id = ue.exercise_id WHERE ue.user_id = users.id AND ({$this->getCompletedCondition()})) DESC")
                ->orderByRaw('(SELECT MAX(updated_at) FROM user_exercises WHERE user_id = users.id) DESC')
                ->limit(10)
                ->get();
        }

        // Si toujours vide, retourner au moins les utilisateurs récents avec activité
        if ($powerUsersQuery->isEmpty()) {
            $powerUsersQuery = DB::table('users')
                ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                ->select(
                    'users.id as user_id',
                    'users.full_name',
                    DB::raw('COALESCE(user_profiles.total_xp, 0) as total_xp_from_profile'),
                    DB::raw("(SELECT COALESCE(SUM(e.xp_value), 0) 
                             FROM user_exercises ue 
                             JOIN exercises e ON e.id = ue.exercise_id 
                             WHERE ue.user_id = users.id 
                             AND ({$this->getCompletedCondition()})) as total_xp_calculated"),
                    DB::raw("(SELECT COUNT(DISTINCT exercise_id) 
                             FROM user_exercises ue
                             JOIN exercises e ON e.id = ue.exercise_id
                             WHERE ue.user_id = users.id 
                             AND ({$this->getCompletedCondition()})) as completed_exercises"),
                    DB::raw('(SELECT MAX(updated_at) 
                             FROM user_exercises 
                             WHERE user_id = users.id) as last_active')
                )
                ->whereExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                             ->from('user_exercises')
                             ->whereColumn('user_exercises.user_id', 'users.id');
                })
                ->groupBy('users.id', 'users.full_name', 'user_profiles.total_xp')
                ->orderByRaw('(SELECT MAX(updated_at) FROM user_exercises WHERE user_id = users.id) DESC')
                ->orderByRaw("GREATEST(COALESCE(user_profiles.total_xp, 0), (SELECT COALESCE(SUM(e.xp_value), 0) FROM user_exercises ue JOIN exercises e ON e.id = ue.exercise_id WHERE ue.user_id = users.id AND ({$this->getCompletedCondition()}))) DESC")
                ->limit(10)
                ->get();
        }

        $powerUsersEnriched = [];
        foreach ($powerUsersQuery as $user) {
            // Utiliser l'XP du profil s'il existe, sinon l'XP calculé
            $totalXp = max((int) ($user->total_xp_from_profile ?? 0), (int) ($user->total_xp_calculated ?? 0));
            
            $powerUsersEnriched[] = [
                'id' => $user->user_id,
                'name' => $user->full_name ?? 'Unknown',
                'total_xp' => $totalXp,
                'status' => 'VIP',
                'last_active' => $user->last_active ? Carbon::parse($user->last_active)->toIso8601String() : null
            ];
        }

        return [
            'churn_risk' => $churnRiskEnriched,
            'power_users' => $powerUsersEnriched
        ];
    }
}

