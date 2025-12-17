<?php

namespace App\UseCases\Exercise;

use App\Repositories\Exercise\ExerciseRepositoryInterface;
use App\Repositories\Level\LevelRepositoryInterface;
use App\Repositories\UserExercise\UserExerciseRepositoryInterface;
use Illuminate\Validation\ValidationException;

class FindExerciseByIdUseCase
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
        private LevelRepositoryInterface $levelRepository,
        private UserExerciseRepositoryInterface $userExerciseRepository
    ) {}

    public function execute(string $exerciseId, ?string $userId = null): array
    {
        // Récupération de l'exercice
        $exercise = $this->exerciseRepository->findById($exerciseId);
        if (!$exercise) {
            throw ValidationException::withMessages([
                'exercise' => ['Exercice non trouvé']
            ]);
        }

        // Récupération du niveau complet
        $level = null;
        if ($exercise->getLevelId()) {
            $level = $this->levelRepository->findById($exercise->getLevelId());
        }

        // Construction de la réponse de base
        $response = [
            'id' => $exercise->getId(),
            'title' => $exercise->getTitle(),
            'description' => $exercise->getDescription(),
            'banner_url' => $exercise->getBannerUrl(),
            'video_url' => $exercise->getVideoUrl(),
            'duration_seconds' => $exercise->getDuration(),
            'xp_value' => $exercise->getXpValue(),
            'level' => null,
        ];

        // Ajout des informations du niveau si disponible
        if ($level) {
            $response['level'] = [
                'id' => $level->getId(),
                'name' => $level->getName(),
                'number' => $level->getLevelNumber(),
            ];
        }

        // Ajout des progrès utilisateur si userId est fourni
        if ($userId) {
            $userExercises = $this->userExerciseRepository->findByUserAndExercise($userId, $exerciseId);
            
            // Calcul du watch_time total et vérification de la complétion
            $totalWatchTime = 0;
            $isCompleted = false;
            $lastAccessedAt = null;
            
            // Marge d'erreur de 4 secondes pour considérer un exercice comme complété
            $toleranceSeconds = 4;
            $exerciseDuration = $exercise->getDuration();
            
            foreach ($userExercises as $userExercise) {
                $totalWatchTime += $userExercise['watch_time'] ?? 0;
                // Considérer comme complété si completed_at existe OU si watch_time >= (duration - 4)
                $watchTime = $userExercise['watch_time'] ?? 0;
                if (!is_null($userExercise['completed_at'] ?? null) || $watchTime >= ($exerciseDuration - $toleranceSeconds)) {
                    $isCompleted = true;
                }
                // Récupérer le dernier updated_at comme last_accessed_at
                if (isset($userExercise['updated_at'])) {
                    $updatedAt = $userExercise['updated_at'];
                    // Comparer les timestamps pour trouver le plus récent
                    $timestamp = strtotime($updatedAt);
                    if (!$lastAccessedAt || $timestamp > strtotime($lastAccessedAt)) {
                        $lastAccessedAt = $updatedAt;
                    }
                }
            }

            $response['user_progress'] = [
                'is_completed' => $isCompleted,
                'watch_time' => $totalWatchTime,
            ];

            if ($lastAccessedAt) {
                // updated_at est déjà au format ISO 8601 depuis le repository
                $response['user_progress']['last_accessed_at'] = $lastAccessedAt;
            }
        }

        return $response;
    }
}

