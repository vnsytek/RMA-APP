<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('rma_ticket_status_log_id')->nullable()->after('rma_ticket_id')->constrained();
            $table->string('stage', 30)->default('other')->after('rma_ticket_status_log_id');

            $table->index(['rma_ticket_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['rma_ticket_id', 'stage']);
            $table->dropConstrainedForeignId('rma_ticket_status_log_id');
            $table->dropColumn('stage');
        });
    }
};
