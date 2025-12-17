<?php

namespace App\Repositories\UserExercise;

use App\Entities\UserExercise;
use App\Models\UserExerciseModel;
use Illuminate\Support\Facades\DB;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UserExerciseRepository implements UserExerciseRepositoryInterface
{
    // Marge d'erreur pour considérer un exercice comme complété (en secondes)
    private const COMPLETION_TOLERANCE_SECONDS = 4;

    /**
     * Détermine si un exercice est complété en tenant compte de la marge d'erreur
     * Un exercice est complété si completed_at IS NOT NULL OU si watch_time >= (duration - tolerance)
     */
    private function isExerciseCompleted(?string $completedAt, int $watchTime, int $duration): bool
    {
        if ($completedAt !== null) {
            return true;
        }
        return $watchTime >= ($duration - self::COMPLETION_TOLERANCE_SECONDS);
    }

    /**
     * Retourne la condition SQL pour vérifier si un exercice est complété
     */
    private function getCompletedCondition(): string
    {
        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
        return "user_exercises.completed_at IS NOT NULL OR (user_exercises.watch_time >= exercises.duration - {$tolerance})";
    }

    public function findByUserAndExercise(string $userId, string $exerciseId): array
    {
        return UserExerciseModel::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->select(['id', 'watch_time', 'completed_at', 'updated_at'])
            ->get()
            ->map(function ($userExercise) {
                return [
                    'id' => $userExercise->id,
                    'watch_time' => $userExercise->watch_time,
                    'completed_at' => $userExercise->completed_at?->toIso8601String(),
                    'updated_at' => $userExercise->updated_at->toIso8601String(),
                ];
            })
            ->toArray();
    }

    public function save(UserExercise $userExercise): UserExercise
    {
        $model = UserExerciseModel::find($userExercise->getId());

        if (!$model) {
            $model = new UserExerciseModel();
            $model->id = (string) Str::uuid();
            $model->user_id = $userExercise->getUserId();
            $model->exercise_id = $userExercise->getExerciseId();
            $model->created_at = $userExercise->getCreatedAt();
        }

        $model->watch_time = $userExercise->getWatchTime();
        $model->completed_at = $userExercise->getCompletedAt();
        $model->save();

        return $model->toEntity();
    }

    public function updateWatchTime(string $userId, string $exerciseId, int $watchTime): UserExercise
    {
        $model = UserExerciseModel::updateOrCreate(
            ['user_id' => $userId, 'exercise_id' => $exerciseId],
            ['watch_time' => $watchTime]
        );
        return $model->toEntity();
    }

    public function markAsCompleted(string $userId, string $exerciseId): void
    {
        UserExerciseModel::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->update(['completed_at' => now()]);
    }

    public function findRecent(string $userId, int $limit): array
    {
        return UserExerciseModel::where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get(['exercise_id', 'completed_at', 'updated_at'])
            ->map(function ($userExercise) {
                return [
                    'exercise_id' => $userExercise->exercise_id,
                    'completed_at' => $userExercise->completed_at?->toIso8601String(),
                    'updated_at' => $userExercise->updated_at->toIso8601String()
                ];
            })
            ->toArray();
    }

    public function findByUserAndExerciseForDate(string $userId, string $exerciseId, DateTime $date): ?UserExercise
    {
        $model = UserExerciseModel::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->whereDate('created_at', $date)
            ->first();

        return $model ? $model->toEntity() : null;
    }

    public function findCompletedByPeriod(string $userId, DateTime $start, DateTime $end): array
    {
        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
        return UserExerciseModel::select(
                'user_exercises.id',
                'user_exercises.exercise_id',
                'user_exercises.completed_at',
                'user_exercises.watch_time',
                'user_exercises.updated_at',
                'user_exercises.created_at'
            )
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->where('user_exercises.user_id', $userId)
            ->whereBetween('user_exercises.created_at', [$start, $end])
            ->whereRaw("({$this->getCompletedCondition()})")
            ->get()
            ->toArray();
    }

    public function findByPeriod(string $userId, DateTime $start, DateTime $end): array
    {
        return UserExerciseModel::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->select(['id', 'exercise_id', 'watch_time', 'created_at'])
            ->get()
            ->toArray();
    }

    public function findCompletedByUserId(string $userId): array
    {
        return UserExerciseModel::select(
                'user_exercises.id',
                'user_exercises.exercise_id',
                'user_exercises.completed_at',
                'user_exercises.watch_time'
            )
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->where('user_exercises.user_id', $userId)
            ->whereRaw("({$this->getCompletedCondition()})")
            ->get()
            ->toArray();
    }

    public function calculateStreak(string $userId, Carbon $startDate, Carbon $endDate): int
    {
        $streak = 0;
        $currentDate = $endDate->copy();

        while ($currentDate->greaterThanOrEqualTo($startDate)) {
            $hasExercise = UserExerciseModel::where('user_id', $userId)
                ->whereDate('created_at', $currentDate->format('Y-m-d'))
                ->exists();

            if (!$hasExercise) {
                break;
            }

            $streak++;
            $currentDate->subDay();
        }

        return $streak;
    }

    public function findStatsForUsers(array $userIds, Carbon $startDate, Carbon $endDate): array
    {
        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
        $completedCondition = $this->getCompletedCondition();

        return UserExerciseModel::select([
                'user_exercises.user_id',
                DB::raw("COALESCE(SUM(CASE WHEN {$completedCondition} THEN exercises.xp_value ELSE 0 END), 0) as total_xp"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$completedCondition} THEN DATE(user_exercises.updated_at) END) as streak")
            ])
            ->whereIn('user_exercises.user_id', $userIds)
            ->whereBetween('user_exercises.updated_at', [$startDate, $endDate])
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->groupBy('user_exercises.user_id')
            ->get()
            ->keyBy('user_id')
            ->toArray();
    }

    public function findAllUserExerciseDates(array $userIds, Carbon $startDate, Carbon $endDate): array
    {
        return UserExerciseModel::select([
                'user_exercises.user_id',
                'user_exercises.exercise_id',
                'user_exercises.created_at',
                'user_exercises.completed_at',
                'user_exercises.watch_time',
                'exercises.xp_value',
                'exercises.duration'
            ])
            ->whereIn('user_exercises.user_id', $userIds)
            ->whereBetween('user_exercises.created_at', [$startDate, $endDate])
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->get()
            ->groupBy('user_id')
            ->map(function ($userExercises) {
                return [
                    'exercises' => $userExercises->map(function ($exercise) {
                        // Déterminer si l'exercice est complété avec la marge d'erreur
                        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
                        $isCompleted = !is_null($exercise->completed_at) ||
                                      ($exercise->watch_time >= ($exercise->duration - $tolerance));

                        return [
                            'exercise_id' => $exercise->exercise_id,
                            'created_at' => $exercise->created_at,
                            'completed_at' => $isCompleted ? ($exercise->completed_at ?? $exercise->created_at) : null,
                            'xp_value' => $exercise->xp_value
                        ];
                    })->toArray()
                ];
            })
            ->toArray();
    }

    // Dashboard stats methods
    public function countActiveUsersByPeriod(Carbon $start, Carbon $end): int
    {
        return UserExerciseModel::whereBetween('updated_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');
    }

    public function countDailyActiveUsers(Carbon $date): int
    {
        return UserExerciseModel::whereDate('updated_at', $date)
            ->distinct('user_id')
            ->count('user_id');
    }

    public function countWeeklyActiveUsers(Carbon $start, Carbon $end): int
    {
        return UserExerciseModel::whereBetween('updated_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');
    }

    public function getActivityByDateRange(Carbon $start, Carbon $end): array
    {
        $results = UserExerciseModel::select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw('COUNT(DISTINCT user_id) as dau')
            )
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get();

        $activityCurve = [];
        foreach ($results as $item) {
            $date = Carbon::parse($item->date);
            $weekStart = $date->copy()->subDays(7);

            // Calculer WAU pour cette date (7 jours précédents)
            $wau = UserExerciseModel::whereBetween('updated_at', [$weekStart, $date->copy()->endOfDay()])
                ->distinct('user_id')
                ->count('user_id');

            $dau = (int) $item->dau;
            $activityCurve[] = [
                'date' => $item->date,
                'dau' => $dau,
                'wau' => $wau,
                'stickiness' => $wau > 0 ? round(($dau / $wau) * 100, 1) : 0
            ];
        }

        return $activityCurve;
    }

    public function getActivityByHourRange(Carbon $start, Carbon $end): array
    {
        $results = UserExerciseModel::select(
                DB::raw('EXTRACT(HOUR FROM updated_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('updated_at', [$start, $end])
            ->groupBy(DB::raw('EXTRACT(HOUR FROM updated_at)'))
            ->get()
            ->keyBy('hour')
            ->toArray();

        // Grouper par plages horaires
        $ranges = [
            '00-04' => [0, 1, 2, 3, 4],
            '04-08' => [4, 5, 6, 7, 8],
            '08-12' => [8, 9, 10, 11, 12],
            '12-16' => [12, 13, 14, 15, 16],
            '16-20' => [16, 17, 18, 19, 20],
            '20-24' => [20, 21, 22, 23]
        ];

        $heatmap = [];
        foreach ($ranges as $range => $hours) {
            $value = 0;
            foreach ($hours as $hour) {
                if (isset($results[$hour])) {
                    $value += $results[$hour]['count'];
                }
            }
            $heatmap[] = [
                'hour_range' => $range,
                'value' => $value
            ];
        }

        return $heatmap;
    }

    public function getResurrectionUsers(Carbon $start, Carbon $end): int
    {
        // Utilisateurs avec dernière activité > 30 jours avant la période ET activité dans la période
        $thirtyDaysBeforeStart = $start->copy()->subDays(30);

        // Trouver les utilisateurs inactifs depuis plus de 30 jours
        $inactiveUsers = DB::table('user_exercises')
            ->select('user_id', DB::raw('MAX(updated_at) as last_activity'))
            ->groupBy('user_id')
            ->havingRaw('MAX(updated_at) <= ?', [$thirtyDaysBeforeStart])
            ->pluck('user_id')
            ->toArray();

        if (empty($inactiveUsers)) {
            return 0;
        }

        // Compter ceux qui ont eu une activité dans la période
        return UserExerciseModel::whereIn('user_id', $inactiveUsers)
            ->whereBetween('updated_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');
    }

    public function getChurnRiskUsers(int $daysInactive, ?Carbon $referenceDate = null): array
    {
        // Utiliser la date de référence (end_date) ou maintenant par défaut
        $refDate = $referenceDate ?? Carbon::now();
        $cutoffDate = $refDate->copy()->subDays($daysInactive);
        $thirtyDaysAgo = $refDate->copy()->subDays(30);

        // Utiliser des bindings pour éviter les injections SQL
        $refDateStr = $refDate->format('Y-m-d H:i:s');

        return UserExerciseModel::select(
                'user_exercises.user_id',
                DB::raw("MAX(user_exercises.updated_at) as last_active"),
                DB::raw("EXTRACT(EPOCH FROM (?::timestamp - MAX(user_exercises.updated_at))) / 86400 as days_inactive")
            )
            ->addBinding($refDateStr, 'select')
            ->where('user_exercises.updated_at', '>=', $thirtyDaysAgo)
            ->where('user_exercises.updated_at', '<=', $cutoffDate)
            ->groupBy('user_exercises.user_id')
            ->havingRaw("EXTRACT(EPOCH FROM (?::timestamp - MAX(user_exercises.updated_at))) / 86400 >= 14", [$refDateStr])
            ->havingRaw("EXTRACT(EPOCH FROM (?::timestamp - MAX(user_exercises.updated_at))) / 86400 < 30", [$refDateStr])
            ->get()
            ->toArray();
    }

    public function getAverageSessionDuration(Carbon $start, Carbon $end): float
    {
        $result = UserExerciseModel::select(
                DB::raw('AVG(watch_time) as avg_duration')
            )
            ->whereBetween('created_at', [$start, $end])
            ->first();

        return $result ? (float) $result->avg_duration : 0.0;
    }

    public function getAverageSessionsPerUser(Carbon $start, Carbon $end): float
    {
        $totalSessions = UserExerciseModel::whereBetween('created_at', [$start, $end])
            ->select(DB::raw('COUNT(DISTINCT (user_id, DATE(created_at))) as total'))
            ->first();

        $totalUsers = UserExerciseModel::whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        if ($totalUsers == 0) {
            return 0.0;
        }

        // Compter les jours distincts avec activité par utilisateur
        $sessionsData = UserExerciseModel::whereBetween('created_at', [$start, $end])
            ->select('user_id', DB::raw('COUNT(DISTINCT DATE(created_at)) as session_count'))
            ->groupBy('user_id')
            ->get();

        $totalSessionsCount = $sessionsData->sum('session_count');

        return $totalUsers > 0 ? round($totalSessionsCount / $totalUsers, 1) : 0.0;
    }

    public function getRetentionCohorts(Carbon $start, Carbon $end): array
    {
        // Cohorts hebdomadaires
        $cohorts = [];
        $current = $start->copy()->startOfWeek();

        while ($current->lte($end)) {
            $weekEnd = $current->copy()->endOfWeek();
            if ($weekEnd->gt($end)) {
                $weekEnd = $end->copy();
            }

            // Nouveaux utilisateurs cette semaine
            $newUsers = DB::table('users')
                ->whereBetween('created_at', [$current, $weekEnd])
                ->pluck('id')
                ->toArray();

            if (empty($newUsers)) {
                $current->addWeek();
                continue;
            }

            $newUsersCount = count($newUsers);

            // D1 retention - vérifier activité le jour suivant l'inscription
            $d1Active = 0;
            foreach ($newUsers as $userId) {
                $user = DB::table('users')->where('id', $userId)->first();
                if ($user) {
                    $userCreatedAt = Carbon::parse($user->created_at);
                    $d1Date = $userCreatedAt->copy()->addDay();
                    $hasActivity = UserExerciseModel::where('user_id', $userId)
                        ->whereRaw('DATE(created_at) = ?', [$d1Date->format('Y-m-d')])
                        ->exists();
                    if ($hasActivity) {
                        $d1Active++;
                    }
                }
            }

            // D7 retention
            $d7Date = $current->copy()->addDays(7);
            $d7Active = UserExerciseModel::whereIn('user_id', $newUsers)
                ->whereBetween('created_at', [$current, $d7Date])
                ->distinct('user_id')
                ->count('user_id');

            // D30 retention
            $d30Date = $current->copy()->addDays(30);
            $d30Active = UserExerciseModel::whereIn('user_id', $newUsers)
                ->whereBetween('created_at', [$current, $d30Date])
                ->distinct('user_id')
                ->count('user_id');

            $cohorts[] = [
                'week_start' => $current->format('Y-m-d'),
                'new_users' => $newUsersCount,
                'd1_percentage' => $newUsersCount > 0 ? round(($d1Active / $newUsersCount) * 100, 1) : 0,
                'd7_percentage' => $newUsersCount > 0 ? round(($d7Active / $newUsersCount) * 100, 1) : 0,
                'd30_percentage' => $newUsersCount > 0 ? round(($d30Active / $newUsersCount) * 100, 1) : 0,
            ];

            $current->addWeek();
        }

        return $cohorts;
    }

    public function getExerciseCompletionStats(Carbon $start, Carbon $end): array
    {
        $stats = UserExerciseModel::select(
                'user_exercises.exercise_id',
                DB::raw('COUNT(*) as started'),
                DB::raw("COUNT(CASE WHEN {$this->getCompletedCondition()} THEN 1 END) as completed")
            )
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->whereBetween('user_exercises.created_at', [$start, $end])
            ->groupBy('user_exercises.exercise_id')
            ->get()
            ->map(function ($item) {
                return [
                    'exercise_id' => $item->exercise_id,
                    'started' => (int) $item->started,
                    'completed' => (int) $item->completed,
                    'completion_rate' => $item->started > 0 ? round(($item->completed / $item->started) * 100, 1) : 0
                ];
            })
            ->toArray();

        $totalStarted = array_sum(array_column($stats, 'started'));
        $totalCompleted = array_sum(array_column($stats, 'completed'));

        return [
            'total_started' => $totalStarted,
            'total_completed' => $totalCompleted,
            'overall_completion_rate' => $totalStarted > 0 ? round(($totalCompleted / $totalStarted) * 100, 1) : 0,
            'by_exercise' => $stats
        ];
    }

    public function getPopularExercises(Carbon $start, Carbon $end, int $limit = 10): array
    {
        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
        return UserExerciseModel::select(
                'user_exercises.exercise_id',
                DB::raw('COUNT(DISTINCT user_exercises.user_id) as views'),
                DB::raw("COUNT(CASE WHEN {$this->getCompletedCondition()} THEN 1 END) as completed"),
                DB::raw('COUNT(*) as total_attempts')
            )
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->whereBetween('user_exercises.created_at', [$start, $end])
            ->groupBy('user_exercises.exercise_id')
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->exercise_id,
                    'views' => (int) $item->views,
                    'completed' => (int) $item->completed,
                    'completion_rate' => $item->total_attempts > 0 ? round(($item->completed / $item->total_attempts) * 100, 1) : 0
                ];
            })
            ->toArray();
    }

    public function getHighDropoffExercises(Carbon $start, Carbon $end, int $limit = 10): array
    {
        $tolerance = self::COMPLETION_TOLERANCE_SECONDS;
        $completedCondition = $this->getCompletedCondition();

        return UserExerciseModel::select(
                'user_exercises.exercise_id',
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw("COUNT(CASE WHEN NOT ({$completedCondition}) THEN 1 END) as dropped"),
                DB::raw("AVG(CASE WHEN NOT ({$completedCondition}) THEN user_exercises.watch_time END) as avg_time_before_drop")
            )
            ->join('exercises', 'user_exercises.exercise_id', '=', 'exercises.id')
            ->whereBetween('user_exercises.created_at', [$start, $end])
            ->groupBy('user_exercises.exercise_id')
            ->havingRaw("COUNT(CASE WHEN NOT ({$completedCondition}) THEN 1 END) > 0")
            ->orderByRaw("(COUNT(CASE WHEN NOT ({$completedCondition}) THEN 1 END)::float / COUNT(*)::float) DESC")
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->exercise_id,
                    'dropoff_rate' => $item->total_attempts > 0 ? round(($item->dropped / $item->total_attempts) * 100, 1) : 0,
                    'avg_time_before_drop' => $item->avg_time_before_drop ? (int) round($item->avg_time_before_drop) : 0
                ];
            })
            ->toArray();
    }
}
