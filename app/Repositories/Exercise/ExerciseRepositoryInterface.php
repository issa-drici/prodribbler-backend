<?php

namespace App\Repositories\Exercise;

use App\Entities\Exercise;

interface ExerciseRepositoryInterface
{
    public function findAll(): array;
    public function findByIds(array $ids): array;
    public function findById(string $id): ?Exercise;
    public function findCompletedExerciseIds(string $userId): array;
    public function findByLevelId(string $levelId): array;
    public function update(string $id, array $data): ?Exercise;
}
