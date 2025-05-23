<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Repositories\UserExercise\UserExerciseRepository;
use App\Repositories\UserExercise\UserExerciseRepositoryInterface;
use App\Repositories\Exercise\ExerciseRepository;
use App\Repositories\Exercise\ExerciseRepositoryInterface;
use App\Repositories\UserProfile\UserProfileRepository;
use App\Repositories\UserProfile\UserProfileRepositoryInterface;
use App\Repositories\Favorite\FavoriteRepositoryInterface;
use App\Repositories\Favorite\FavoriteRepository;
use App\Repositories\File\FileRepository;
use App\Repositories\File\FileRepositoryInterface;
use App\Repositories\Level\LevelRepository;
use App\Repositories\Level\LevelRepositoryInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\Repositories\User\UserRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserExerciseRepositoryInterface::class, UserExerciseRepository::class);
        $this->app->bind(ExerciseRepositoryInterface::class, ExerciseRepository::class);
        $this->app->bind(UserProfileRepositoryInterface::class, UserProfileRepository::class);
        $this->app->bind(FavoriteRepositoryInterface::class, FavoriteRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(FileRepositoryInterface::class, FileRepository::class);
        $this->app->bind(LevelRepositoryInterface::class, LevelRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

    }
}
