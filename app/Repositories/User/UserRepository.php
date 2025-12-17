<?php

namespace App\Repositories\User;

use App\Models\UserModel;
use App\Models\FileModel;
use App\Models\SupportRequestModel;
use App\Models\FavoriteModel;
use App\Models\UserExerciseModel;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?array
    {
        return UserModel::where('id', $id)
            ->select(['id', 'full_name', 'email'])
            ->first()?->toArray();
    }

    public function findAll(): array
    {
        return UserModel::select(['id', 'full_name', 'email'])
            ->get()
            ->toArray();
    }

    public function deleteUserData(string $userId): bool
    {
        try {
            DB::beginTransaction();

            // Suppression des fichiers (avatars)
            FileModel::where('user_id', $userId)->delete();

            // Suppression des requêtes de support
            SupportRequestModel::where('user_id', $userId)->delete();

            // Suppression des favoris
            FavoriteModel::where('user_id', $userId)->delete();

            // Suppression des exercices utilisateur
            UserExerciseModel::where('user_id', $userId)->delete();

            // Suppression du profil utilisateur (cascade delete configuré)
            // Suppression de l'utilisateur (cascade delete configuré)
            UserModel::where('id', $userId)->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function findAdminUsers(array $filters, int $page = 1, int $perPage = 10): array
    {
        $query = UserModel::select([
                'users.id',
                'users.full_name',
                'users.email',
                'users.role',
                'users.created_at',
                'users.updated_at',
                DB::raw('COALESCE(user_profiles.total_xp, 0) as total_xp'),
                DB::raw('COALESCE(user_profiles.total_training_time, 0) as total_training_time'),
                DB::raw('COALESCE(user_profiles.completed_videos, 0) as completed_videos'),
                DB::raw('COALESCE(user_profiles.completed_days, 0) as completed_days'),
                DB::raw('user_profiles.avatar_file_id'),
                // Last active: MAX entre user_exercises.updated_at, sessions.last_activity (converti), users.updated_at
                DB::raw("COALESCE(
                    (SELECT MAX(activity_ts)
                     FROM (
                         SELECT MAX(updated_at) as activity_ts FROM user_exercises WHERE user_id = users.id
                         UNION ALL
                         SELECT TO_TIMESTAMP(MAX(last_activity)) as activity_ts FROM sessions WHERE user_id = users.id AND last_activity IS NOT NULL
                         UNION ALL
                         SELECT users.updated_at as activity_ts
                     ) subq),
                    users.created_at
                ) as last_active")
            ])
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id');

        // Recherche par nom ou email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('users.full_name', 'ILIKE', "%{$search}%")
                  ->orWhere('users.email', 'ILIKE', "%{$search}%");
            });
        }

        // Filtre par rôle
        if (!empty($filters['role'])) {
            $query->where('users.role', $filters['role']);
        }


        // Tri
        $sortBy = $filters['sort_by'] ?? 'full_name';
        $order = strtolower($filters['order'] ?? 'asc');
        
        // Validation du champ de tri
        $allowedSortFields = ['created_at', 'full_name', 'xp', 'last_active', 'completed_videos', 'total_training_time'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'full_name';
        }
        
        // Validation de l'ordre
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        // Mapping des champs de tri
        $sortFieldMap = [
            'created_at' => 'users.created_at',
            'full_name' => 'users.full_name',
            'xp' => 'total_xp',
            'last_active' => 'last_active',
            'completed_videos' => 'completed_videos',
            'total_training_time' => 'total_training_time'
        ];

        $query->orderBy($sortFieldMap[$sortBy], $order);

        // Pagination
        $total = $query->count();
        $users = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $users->toArray(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage)
        ];
    }
}