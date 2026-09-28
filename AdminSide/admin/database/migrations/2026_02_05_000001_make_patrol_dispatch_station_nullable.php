<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * PostgreSQL aborts entire transaction on any error — run without transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('patrol_dispatches')) {
            return;
        }

        // Drop FK first so we can alter nullability safely (IF EXISTS prevents errors).
        DB::statement('ALTER TABLE "patrol_dispatches" DROP CONSTRAINT IF EXISTS "patrol_dispatches_station_id_foreign"');

        // Make station_id nullable (Postgres-safe).
        DB::statement("ALTER TABLE patrol_dispatches ALTER COLUMN station_id DROP NOT NULL");

        // Re-add FK with SET NULL since column is now nullable.
        $fkExists = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = ? AND table_name = ? AND table_schema = 'public'",
            ['patrol_dispatches_station_id_foreign', 'patrol_dispatches']
        );
        if (empty($fkExists)) {
            Schema::table('patrol_dispatches', function (Blueprint $table) {
                $table->foreign('station_id')
                    ->references('station_id')
                    ->on('police_stations')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('patrol_dispatches')) {
            return;
        }

        // Drop FK safely
        DB::statement('ALTER TABLE "patrol_dispatches" DROP CONSTRAINT IF EXISTS "patrol_dispatches_station_id_foreign"');

        // If there are NULL station_id rows, try to backfill with the first station.
        $fallbackStationId = DB::table('police_stations')->orderBy('station_id')->value('station_id');
        if ($fallbackStationId) {
            DB::table('patrol_dispatches')->whereNull('station_id')->update(['station_id' => $fallbackStationId]);
        }

        DB::statement("ALTER TABLE patrol_dispatches ALTER COLUMN station_id SET NOT NULL");

        // Restore original FK behavior (cascade) to match initial schema.
        $fkExists = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = ? AND table_name = ? AND table_schema = 'public'",
            ['patrol_dispatches_station_id_foreign', 'patrol_dispatches']
        );
        if (empty($fkExists)) {
            Schema::table('patrol_dispatches', function (Blueprint $table) {
                $table->foreign('station_id')
                    ->references('station_id')
                    ->on('police_stations')
                    ->onDelete('cascade');
            });
        }
    }
};
