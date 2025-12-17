<?php

namespace App\Http\Controllers\Exercise;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExerciseRequest;
use App\UseCases\Exercise\UpdateExerciseUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateExerciseAction extends Controller
{
    public function __construct(
        private UpdateExerciseUseCase $useCase
    ) {}

    /**
     * Met à jour les détails d'un exercice
     * 
     * @param UpdateExerciseRequest $request
     * @param string $id L'ID de l'exercice à mettre à jour
     * 
     * @return JsonResponse
     */
    public function __invoke(UpdateExerciseRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->useCase->execute($id, $request->validated());
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            $statusCode = $e->errors()['exercise'] ?? null 
                ? (str_contains($e->errors()['exercise'][0], 'non trouvé') ? 404 : 422)
                : 422;
            
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

