<?php

namespace App\Repositories\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?array;
    public function findAll(): array;
    public function deleteUserData(string $userId): bool;
    public function findAdminUsers(array $filters, int $page = 1, int $perPage = 10): array;
}