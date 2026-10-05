<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo scope: quantity_on_hand is the source of truth and gets written to directly by
 * stock-in/stock-out; part_movements is just a log for display, not something quantities
 * are summed from. A full append-only ledger can replace this later if it's worth the cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('unit', 20)->default('cái');
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('min_quantity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('part_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->unsignedInteger('quantity');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_movements');
        Schema::dropIfExists('parts');
    }
};
