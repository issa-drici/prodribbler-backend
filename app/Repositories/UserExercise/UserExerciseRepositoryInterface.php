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
}
