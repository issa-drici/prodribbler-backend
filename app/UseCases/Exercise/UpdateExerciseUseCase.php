<?php

namespace App\UseCases\Exercise;

use App\Repositories\Exercise\ExerciseRepositoryInterface;
use App\Repositories\Level\LevelRepositoryInterface;
use Illuminate\Validation\ValidationException;

class UpdateExerciseUseCase
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
        private LevelRepositoryInterface $levelRepository
    ) {}

    public function execute(string $exerciseId, array $data): array
    {
        // Vérifier que l'exercice existe
        $exercise = $this->exerciseRepository->findById($exerciseId);
        if (!$exercise) {
            throw ValidationException::withMessages([
                'exercise' => ['Exercice non trouvé']
            ]);
        }

        // Préparer les données pour la mise à jour
        $updateData = [];

        // Mapper les champs de la requête vers les champs de la base de données
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['duration_seconds'])) {
            $updateData['duration'] = $data['duration_seconds'];
        }

        if (isset($data['xp_value'])) {
            $updateData['xp_value'] = $data['xp_value'];
        }

        if (isset($data['banner_url'])) {
            $updateData['banner_url'] = $data['banner_url'];
        }

        if (isset($data['video_url'])) {
            $updateData['video_url'] = $data['video_url'];
        }

        // Gérer le level_id si fourni
        if (isset($data['level_id'])) {
            // Vérifier que le niveau existe
            $level = $this->levelRepository->findById($data['level_id']);
            if (!$level) {
                throw ValidationException::withMessages([
                    'level_id' => ['Niveau non trouvé']
                ]);
            }
            
            $updateData['level_id'] = $data['level_id'];
            // Mettre à jour aussi le champ level (int) basé sur le level_number
            $updateData['level'] = $level->getLevelNumber();
        }

        // Mettre à jour l'exercice
        $updatedExercise = $this->exerciseRepository->update($exerciseId, $updateData);
        
        if (!$updatedExercise) {
            throw ValidationException::withMessages([
                'exercise' => ['Erreur lors de la mise à jour de l\'exercice']
            ]);
        }

        // Construire la réponse
        $response = [
            'id' => $updatedExercise->getId(),
            'title' => $updatedExercise->getTitle(),
            'description' => $updatedExercise->getDescription(),
            'banner_url' => $updatedExercise->getBannerUrl(),
            'video_url' => $updatedExercise->getVideoUrl(),
            'duration_seconds' => $updatedExercise->getDuration(),
            'xp_value' => $updatedExercise->getXpValue(),
            'level_id' => $updatedExercise->getLevelId(),
        ];

        return $response;
    }
}

