<?php

namespace App\Http\Controllers\User;

use App\UseCases\User\FindAdminUsersUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindAdminUsersAction
{
    public function __construct(
        private FindAdminUsersUseCase $findAdminUsersUseCase
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $filters = [
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 10),
                'search' => $request->query('search'),
                'sort_by' => $request->query('sort_by', 'full_name'),
                'order' => $request->query('order', 'asc'),
                'role' => $request->query('role'),
            ];

            // Nettoyer les valeurs vides
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

            $result = $this->findAdminUsersUseCase->execute($filters);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

