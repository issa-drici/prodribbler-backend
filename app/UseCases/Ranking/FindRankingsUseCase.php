<?php

namespace App\UseCases\Ranking;

use App\Repositories\UserProfile\UserProfileRepositoryInterface;
use App\Repositories\UserExercise\UserExerciseRepositoryInterface;
use App\Repositories\File\FileRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class FindRankingsUseCase
{
    public function __construct(
        private UserProfileRepositoryInterface $userProfileRepository,
        private UserExerciseRepositoryInterface $userExerciseRepository,
        private FileRepositoryInterface $fileRepository
    ) {}

    public function execute(string $type = 'week'): array
    {
        // Validation du type
        if (!in_array($type, ['day', 'week', 'month'])) {
            throw ValidationException::withMessages([
                'type' => ['Le type doit être day, week ou month']
            ]);
        }

        // Calcul de la période
        $endDate = Carbon::now();
        $startDate = match($type) {
            'day' => $endDate->copy()->startOfDay(),
            'week' => $endDate->copy()->subDays(6)->startOfDay(),
            'month' => $endDate->copy()->subDays(29)->startOfDay(),
        };

             // Récupération de tous les utilisateurs
             $allUsers = $this->userProfileRepository->findAllUsers();
             $userIds = array_column($allUsers, 'user_id');

             // Récupération des dates d'exercices pour tous les utilisateurs
             $exerciseDates = $this->userExerciseRepository->findAllUserExerciseDates($userIds, $startDate, $endDate);

             // Fusion des données
             $rankings = array_map(function($user) use ($exerciseDates) {
                 $userStats = $exerciseDates[$user['user_id']] ?? [];
                 $stats = $this->calculateUserStats($userStats['exercises'] ?? []);

                 // Récupérer l'avatar si présent
                 $avatarUrl = null;
                 $userProfile = $this->userProfileRepository->findByUserId($user['user_id']);
                 if ($userProfile && $userProfile->getAvatarFileId()) {
                     $avatarFile = $this->fileRepository->findById($userProfile->getAvatarFileId());
                     if ($avatarFile) {
                         $avatarUrl = $avatarFile->getUrl();
                     }
                 }

                 return [
                     'user_id' => $user['user_id'],
                     'full_name' => $user['full_name'],
                     'avatar_url' => $avatarUrl,
                     'total_xp' => $stats['total_xp'],
                     'streak' => $stats['streak']
                 ];
             }, $allUsers);

        // Tri par XP décroissant
        usort($rankings, fn($a, $b) => $b['total_xp'] - $a['total_xp']);

        // Ajout du rang et identification de l'utilisateur courant
        $currentUser = Auth::user();
        $currentUserRank = 0;
        $formattedRankings = [];

        foreach ($rankings as $index => $ranking) {
            $rank = $index + 1;
            $isCurrentUser = $currentUser && $ranking['user_id'] === $currentUser->id;

            if ($isCurrentUser) {
                $currentUserRank = $rank;
            }

            $formattedRankings[] = [
                'rank' => $rank,
                'user_id' => $ranking['user_id'],
                'full_name' => $ranking['full_name'],
                'avatar_url' => $ranking['avatar_url'],
                'total_xp' => $ranking['total_xp'],
                'streak' => $ranking['streak'],
                'is_current_user' => $isCurrentUser
            ];
        }

        return [
            'current_user_rank' => $currentUserRank,
            'rankings' => $formattedRankings,
            'period' => [
                'type' => $type,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ]
        ];
    }

    private function calculateMaxStreak(array $dates): int
    {
        if (empty($dates)) {
            return 0;
        }

        sort($dates);
        $maxStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $previousDate = Carbon::parse($dates[$i - 1]);
            $currentDate = Carbon::parse($dates[$i]);

            if ($previousDate->addDay()->eq($currentDate)) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }
        }

        return $maxStreak;
    }

    private function calculateUserStats(array $exercises): array
    {
        if (empty($exercises)) {
            return ['total_xp' => 0, 'streak' => 0];
        }

        // Calcul de l'XP total (uniquement pour les exercices complétés, sans doublons)
        $totalXp = collect($exercises)
            ->filter(fn($exercise) => !is_null($exercise['completed_at']))
            ->unique('exercise_id')
            ->sum('xp_value');

        // Calcul du streak (jours consécutifs)
        $dates = collect($exercises)
            ->pluck('created_at')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return [
            'total_xp' => $totalXp,
            'streak' => $this->calculateMaxStreak($dates)
        ];
    }
}
