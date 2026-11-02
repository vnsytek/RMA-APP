<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parts fitted by Sang Y's own technician at the customer's site: they come either from the vendor
 * or from Sang Y's stock, and are identified by serial rather than a vendor case number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rma_replaced_parts', function (Blueprint $table) {
            $table->string('serial_number', 100)->nullable()->after('part_code');
            $table->string('source', 10)->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('rma_replaced_parts', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'source']);
        });
    }
};
