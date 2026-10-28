<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket numbers moved from "YYMM0001" (8 chars) to "RMA-YYMM-001" (12 chars, always
 * exactly 12 — see TicketNumberGenerator). The column was a fixed CHAR(8), so it has to
 * widen or the new format simply won't fit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->char('ticket_no', 12)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->char('ticket_no', 8)->change();
        });
    }
};
