<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only correction log: when staff fix a data-entry mistake (wrong serial, typo'd fault
 * description, a quote line changed after it was already sent), the old and new values are
 * recorded here instead of being silently overwritten. Shown merged into the ticket's status
 * timeline so the record stays an honest account of what actually happened, mistakes included —
 * that's the point of running this on a computer instead of paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rma_ticket_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('field', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['rma_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rma_ticket_edits');
    }
};
