<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Création de la fonction qui compte les vidéos complétées (exercices distincts)
        // Un exercice est considéré comme complété si completed_at IS NOT NULL OU si watch_time >= (duration - 4)
        DB::unprepared('
            CREATE OR REPLACE FUNCTION calculate_completed_videos()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE user_profiles
                SET completed_videos = (
                    SELECT COUNT(DISTINCT ue.exercise_id)
                    FROM user_exercises ue
                    JOIN exercises e ON e.id = ue.exercise_id
                    WHERE ue.user_id = COALESCE(NEW.user_id, OLD.user_id)
                    AND (ue.completed_at IS NOT NULL OR ue.watch_time >= (e.duration - 4))
                )
                WHERE user_id = COALESCE(NEW.user_id, OLD.user_id);
                
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Création du trigger qui s'exécute après INSERT, UPDATE ou DELETE
        DB::unprepared('
            CREATE TRIGGER update_completed_videos_trigger
            AFTER INSERT OR UPDATE OR DELETE ON user_exercises
            FOR EACH ROW
            EXECUTE FUNCTION calculate_completed_videos();
        ');
    }

    public function down(): void
    {
        // Suppression du trigger
        DB::unprepared('DROP TRIGGER IF EXISTS update_completed_videos_trigger ON user_exercises;');
        
        // Suppression de la fonction
        DB::unprepared('DROP FUNCTION IF EXISTS calculate_completed_videos();');
    }
}; 