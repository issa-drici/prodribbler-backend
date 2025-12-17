<?php

namespace App\UseCases\Exercise;

use App\Repositories\Exercise\ExerciseRepositoryInterface;
use App\Repositories\UserExercise\UserExerciseRepositoryInterface;
use App\Repositories\Favorite\FavoriteRepositoryInterface;
use Illuminate\Validation\ValidationException;

class FindExerciseByIdAndUserIdUseCase
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
        private UserExerciseRepositoryInterface $userExerciseRepository,
        private FavoriteRepositoryInterface $favoriteRepository
    ) {}

    public function execute(string $exerciseId, string $userId): array
    {
        // Récupération de l'exercice
        $exercise = $this->exerciseRepository->findById($exerciseId);
        if (!$exercise) {
            throw ValidationException::withMessages([
                'exercise' => ['Exercice non trouvé']
            ]);
        }

        // Récupération des enregistrements user_exercise
        $userExercises = $this->userExerciseRepository->findByUserAndExercise($userId, $exerciseId);
        
        // Calcul du watch_time total et vérification de la complétion
        // Marge d'erreur de 4 secondes : considérer comme complété si watch_time >= (duration - 4)
        $toleranceSeconds = 4;
        $totalWatchTime = 0;
        $isCompleted = false;
        $exerciseDuration = $exercise->getDuration();
        
        foreach ($userExercises as $userExercise) {
            $totalWatchTime += $userExercise['watch_time'];
            // Considérer comme complété si completed_at existe OU si watch_time >= (duration - 4)
            if (!is_null($userExercise['completed_at']) || $userExercise['watch_time'] >= ($exerciseDuration - $toleranceSeconds)) {
                $isCompleted = true;
            }
        }

        // Vérification si l'exercice est en favoris
        $favorite = $this->favoriteRepository->findByUserAndExercise($userId, $exerciseId);

        return [
            'id' => $exercise->getId(),
            'title' => $exercise->getTitle(),
            'description' => $exercise->getDescription(),
            'duration_seconds' => $exercise->getDuration(),
            'level' => $exercise->getLevel(),
            'xp' => $exercise->getXpValue(),
            'banner_url' => $exercise->getBannerUrl(),
            'video_url' => $exercise->getVideoUrl(),
            'user_progress' => [
                'is_completed' => $isCompleted,
                'watch_time' => $totalWatchTime
            ],
            'is_favorite' => !is_null($favorite)
        ];
    }
} 