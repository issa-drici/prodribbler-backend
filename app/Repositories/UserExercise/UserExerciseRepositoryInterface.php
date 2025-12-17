<?php

namespace App\Repositories\UserExercise;

use App\Entities\UserExercise;
use DateTime;
use Carbon\Carbon;

interface UserExerciseRepositoryInterface
{
    public function save(UserExercise $userExercise): UserExercise;
    public function updateWatchTime(string $userId, string $exerciseId, int $watchTime): UserExercise;
    public function markAsCompleted(string $userId, string $exerciseId): void;
    public function findRecent(string $userId, int $limit): array;
    public function findByUserAndExerciseForDate(string $userId, string $exerciseId, DateTime $date): ?UserExercise;
    public function findCompletedByPeriod(string $userId, Carbon $startDate, Carbon $endDate): array;
    public function findByPeriod(string $userId, DateTime $start, DateTime $end): array;
    public function findCompletedByUserId(string $userId): array;
    public function findByUserAndExercise(string $userId, string $exerciseId): ?array;
    public function calculateStreak(string $userId, Carbon $startDate, Carbon $endDate): int;
    public function findStatsForUsers(array $userIds, Carbon $startDate, Carbon $endDate): array;
    public function findAllUserExerciseDates(array $userIds, Carbon $startDate, Carbon $endDate): array;
    
    // Dashboard stats methods
    public function countActiveUsersByPeriod(Carbon $start, Carbon $end): int;
    public function countDailyActiveUsers(Carbon $date): int;
    public function countWeeklyActiveUsers(Carbon $start, Carbon $end): int;
    public function getActivityByDateRange(Carbon $start, Carbon $end): array;
    public function getActivityByHourRange(Carbon $start, Carbon $end): array;
    public function getResurrectionUsers(Carbon $start, Carbon $end): int;
    public function getChurnRiskUsers(int $daysInactive, ?Carbon $referenceDate = null): array;
    public function getAverageSessionDuration(Carbon $start, Carbon $end): float;
    public function getAverageSessionsPerUser(Carbon $start, Carbon $end): float;
    public function getRetentionCohorts(Carbon $start, Carbon $end): array;
    public function getExerciseCompletionStats(Carbon $start, Carbon $end): array;
    public function getPopularExercises(Carbon $start, Carbon $end, int $limit = 10): array;
    public function getHighDropoffExercises(Carbon $start, Carbon $end, int $limit = 10): array;
}
