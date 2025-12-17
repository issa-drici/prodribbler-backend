<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recalcule toutes les valeurs de completed_videos dans user_profiles
     * en comptant uniquement les exercices distincts complétés
     * (avec la marge d'erreur de 4 secondes)
     */
    public function up(): void
    {
        // Recalculer completed_videos pour tous les utilisateurs
        // Un exercice est considéré comme complété si completed_at IS NOT NULL OU si watch_time >= (duration - 4)
        DB::statement('
            UPDATE user_profiles up
            SET completed_videos = (
                SELECT COUNT(DISTINCT ue.exercise_id)
                FROM user_exercises ue
                JOIN exercises e ON e.id = ue.exercise_id
                WHERE ue.user_id = up.user_id
                AND (ue.completed_at IS NOT NULL OR ue.watch_time >= (e.duration - 4))
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback possible sans perdre les données
        // On laisse les valeurs recalculées
    }
};

