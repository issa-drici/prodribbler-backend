<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\FindUserByIdAction;
use App\Http\Controllers\Exercise\{
    FindAllExercisesAction,
    FindExerciseByIdAction,
    FindExerciseByIdAndUserIdAction,
    FindAllExercisesByLevelIdAndUserIdAction,
    FindAllExercisesByUserIdAction,
    FindExercisesByLevelIdAction,
    UpdateExerciseAction
};
use App\Http\Controllers\UserExercise\{
    CompleteUserExerciseAction,
    UpdateUserExerciseProgressAction
};
use App\Http\Controllers\Favorite\{
    CreateFavoriteAction,
    DeleteFavoriteAction,
    FindAllFavoritesAction
};
use App\Http\Controllers\Home\FindHomeDataAction;
use App\Http\Controllers\Level\FindAllLevelsAction;
use App\Http\Controllers\Level\FindLevelsByCategoryAction;
use App\Http\Controllers\Stats\{
    FindUserStatsAction,
    FindDashboardOverviewAction
};
use App\Http\Controllers\Ranking\FindRankingsAction;
use App\Http\Controllers\Profile\{
    FindUserProfileAction,
    UpdateUserGoalsAction,
    UpdateUserAvatarAction
};
use App\Http\Controllers\Support\{
    CreateSupportRequestAction,
    FindAllSupportRequestsAction
};
use App\Http\Controllers\User\DeleteUserDataAction;
use App\Http\Controllers\User\FindAdminUsersAction;
use App\Http\Controllers\Version\VersionCheckAction;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

Route::delete('/user', DeleteUserDataAction::class)->middleware('auth:sanctum');


// Exercise routes
Route::get('/exercises', FindAllExercisesAction::class);
Route::get('/exercises/user/{userId}', FindAllExercisesByUserIdAction::class);
Route::get('/exercises/level/{levelId}/user/{userId}', FindAllExercisesByLevelIdAndUserIdAction::class);
Route::get('/exercises/{exerciseId}/user/{userId}', FindExerciseByIdAndUserIdAction::class);
Route::get('/exercises/{id}', FindExerciseByIdAction::class);
Route::put('/exercises/{id}', UpdateExerciseAction::class)
    ->middleware(['auth:sanctum', \App\Http\Middleware\EnsureUserIsAdmin::class]);
// Route::post('/exercises', CreateExerciseAction::class)
//     ->middleware(['auth:sanctum', \App\Http\Middleware\EnsureUserIsAdmin::class]);

// User Exercise routes
Route::post('/user-exercises/{exerciseId}/complete', CompleteUserExerciseAction::class)->middleware('auth:sanctum');
Route::post('/user-exercises/{exerciseId}/progress', UpdateUserExerciseProgressAction::class)->middleware('auth:sanctum');

// Favorites routes
Route::get('/favorites', FindAllFavoritesAction::class)->middleware('auth:sanctum');
Route::post('/favorites', CreateFavoriteAction::class)->middleware('auth:sanctum');
Route::delete('/favorites/exercise/{exerciseId}', DeleteFavoriteAction::class)->middleware('auth:sanctum');

// Home route
Route::get('/home', FindHomeDataAction::class)->middleware('auth:sanctum');

// Stats routes
Route::get('/user/stats', FindUserStatsAction::class)->middleware('auth:sanctum');
Route::get('/stats/dashboard/overview', FindDashboardOverviewAction::class)
    ->middleware(['auth:sanctum', \App\Http\Middleware\EnsureUserIsAdmin::class]);

// Admin routes
Route::get('/admin/users', FindAdminUsersAction::class)
    ->middleware(['auth:sanctum', \App\Http\Middleware\EnsureUserIsAdmin::class]);

// Rankings route
Route::get('/rankings', FindRankingsAction::class)->middleware('auth:sanctum');

// Profile routes
Route::get('/profile', FindUserProfileAction::class)->middleware('auth:sanctum');
Route::put('/user/goals', UpdateUserGoalsAction::class)->middleware('auth:sanctum');
Route::post('/profile/avatar', UpdateUserAvatarAction::class)->middleware('auth:sanctum');
// Route::put('/profile/email', UpdateUserEmailAction::class)->middleware('auth:sanctum');

// Support routes
Route::post('/support-requests', CreateSupportRequestAction::class)->middleware('auth:sanctum');
Route::get('/support-requests', FindAllSupportRequestsAction::class)->middleware('auth:sanctum');

// User routes
Route::get('/user/{userId}', FindUserByIdAction::class);

// Level routes
Route::get('/levels', FindAllLevelsAction::class);
Route::get('/levels/category/{category}', FindLevelsByCategoryAction::class);
Route::get('/levels/{id}/exercises', FindExercisesByLevelIdAction::class);

// Version routes
Route::get('/version-check', VersionCheckAction::class);
