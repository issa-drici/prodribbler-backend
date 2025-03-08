<?php

namespace App\Repositories\Exercise;

use App\Entities\Exercise;
use App\Models\ExerciseModel;
use App\Models\UserExerciseModel;

class ExerciseRepository implements ExerciseRepositoryInterface
{
    public function findAll(): array
    {
        return ExerciseModel::with('level')
            ->select([
                'id',
                'title',
                'description',
                'duration',
                // 'level',
                'level_id',
                'xp_value',
                'banner_url',
                'video_url'
            ])
            ->get()
            ->map(function ($exercise) {
                $data = $exercise->toArray();
                if ($exercise->level) {
                    $data['level_name'] = $exercise->level->name;
                    $data['level_category'] = $exercise->level->category;
                    $data['level_banner_url'] = $exercise->level->banner_url;
                }
                return $data;
            })
            ->toArray();
    }

    public function findCompletedExerciseIds(string $userId): array
    {
        return UserExerciseModel::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->pluck('exercise_id')
            ->toArray();
    }

    public function findByIds(array $ids): array
    {
        return ExerciseModel::whereIn('id', $ids)
            ->get()
            ->map(function ($exercise) {
                return [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'description' => $exercise->description,
                    'level' => $exercise->level,
                    'duration_seconds' => $exercise->duration,
                    'xp' => $exercise->xp_value,
                    'banner_url' => $exercise->banner_url,
                    'video_url' => $exercise->video_url
                ];
            })
            ->toArray();
    }

    public function findById(string $id): ?Exercise
    {
        $model = ExerciseModel::find($id);
        return $model ? $model->toEntity() : null;
    }

    public function findByLevelId(string $levelId): array
    {
        return ExerciseModel::where('level_id', $levelId)
            ->select([
                'id',
                'title',
                'description',
                'duration',
                'level',
                'level_id',
                'xp_value',
                'banner_url',
                'video_url'
            ])
            ->get()
            ->toArray();
    }
}
