<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_types', function (Blueprint $table) {
            $table->text('warranty_exclusions')->nullable()->after('name');
        });

        Schema::create('rma_warranty_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->foreignId('rma_quote_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedTinyInteger('months');
            $table->timestamps();
        });

        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->text('warranty_exclusions')->nullable()->after('repair_warranty_months');
            $table->foreignId('claim_ticket_id')->nullable()->after('warranty_exclusions')->constrained('rma_tickets');
            $table->foreignId('claim_item_id')->nullable()->after('claim_ticket_id')->constrained('rma_warranty_items');
            $table->string('claim_result', 20)->nullable()->after('claim_item_id');
            $table->text('claim_note')->nullable()->after('claim_result');
        });

        foreach (config('rma.warranty_exclusions') as $code => $exclusions) {
            DB::table('device_types')->where('code', $code)->whereNull('warranty_exclusions')->update(['warranty_exclusions' => $exclusions]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claim_item_id');
            $table->dropConstrainedForeignId('claim_ticket_id');
            $table->dropColumn(['warranty_exclusions', 'claim_result', 'claim_note']);
        });

        Schema::dropIfExists('rma_warranty_items');

        Schema::table('device_types', function (Blueprint $table) {
            $table->dropColumn('warranty_exclusions');
        });
    }
};
