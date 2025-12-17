<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\UseCases\Exercise\FindExerciseByIdUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FindExerciseByIdAction extends Controller
{
    public function __construct(
        private FindExerciseByIdUseCase $useCase
    ) {}

    /**
     * Récupère les détails d'un exercice spécifique
     * 
     * @param Request $request
     * @param string $id L'ID de l'exercice à récupérer
     * 
     * @return JsonResponse
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            // Récupération optionnelle du user_id depuis les query parameters
            $userId = $request->query('user_id');
            
            $result = $this->useCase->execute($id, $userId);
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

