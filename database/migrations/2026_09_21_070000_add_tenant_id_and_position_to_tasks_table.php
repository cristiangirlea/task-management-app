<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tasks carry their tenant directly (denormalised from the project) so
     * that tenant isolation is a single indexed column, and a `position`
     * that orders cards inside a Kanban column independently of `priority`.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0)->after('priority');
            $table->index(['tenant_id', 'project_id', 'status', 'position'], 'tasks_board_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_board_index');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('position');
        });
    }
};
