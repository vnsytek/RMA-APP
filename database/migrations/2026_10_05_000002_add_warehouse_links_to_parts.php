<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->unsignedInteger('warranty_months')->nullable()->after('min_quantity');
        });

        Schema::table('rma_quote_items', function (Blueprint $table) {
            $table->foreignId('part_id')->nullable()->after('rma_ticket_id')->constrained()->nullOnDelete();
        });

        // Adjustment movements can be negative (a stocktake correcting the count downward).
        Schema::table('part_movements', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('part_movements', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->change();
        });

        Schema::table('rma_quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_id');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('warranty_months');
        });
    }
};
