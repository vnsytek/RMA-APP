<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ticket number doubles as the ERP receipt voucher (V223); return vouchers (V233) are unique.
     */
    public function up(): void
    {
        DB::table('rma_tickets')->update(['erp_receipt_no' => DB::raw('ticket_no')]);

        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->unique('erp_return_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->dropUnique(['erp_return_no']);
        });
    }
};
