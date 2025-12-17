<?php

namespace App\UseCases\User;

use App\Repositories\User\UserRepositoryInterface;
use App\Repositories\File\FileRepositoryInterface;
use Carbon\Carbon;

class FindAdminUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private FileRepositoryInterface $fileRepository
    ) {}

    public function execute(array $filters = []): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 10);
        
        // Validation des paramètres
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage)); // Limiter à 100 par page max

        // Récupération des utilisateurs avec pagination
        $result = $this->userRepository->findAdminUsers($filters, $page, $perPage);

        // Enrichissement des données avec avatar_url et formatage
        $enrichedData = array_map(function ($user) {
            $avatarUrl = null;
            if (!empty($user['avatar_file_id'])) {
                $avatarFile = $this->fileRepository->findById($user['avatar_file_id']);
                if ($avatarFile) {
                    $avatarUrl = $avatarFile->getUrl();
                }
            }

            $lastActive = $user['last_active'] ? Carbon::parse($user['last_active']) : null;
            $createdAt = $user['created_at'] ? Carbon::parse($user['created_at']) : null;

            return [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'created_at' => $createdAt ? $createdAt->toIso8601String() : null,
                'last_active' => $lastActive ? $lastActive->toIso8601String() : null,
                'total_xp' => (int) ($user['total_xp'] ?? 0),
                'total_training_time' => (int) ($user['total_training_time'] ?? 0), // en secondes
                'completed_videos' => (int) ($user['completed_videos'] ?? 0),
                'completed_days' => (int) ($user['completed_days'] ?? 0),
                'avatar_url' => $avatarUrl
            ];
        }, $result['data']);

        return [
            'data' => $enrichedData,
            'meta' => [
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total']
            ]
        ];
    }
}

