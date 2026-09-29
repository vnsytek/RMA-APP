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
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->string('ticket_type', 10);
            $table->char('period', 4);
            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['ticket_type', 'period']);
        });

        Schema::create('rma_tickets', function (Blueprint $table) {
            $table->id();
            $table->char('ticket_no', 8)->unique();
            $table->string('service_type', 20);
            $table->string('original_service_type', 20);
            $table->string('onsite_location', 20)->nullable();
            $table->string('warranty_status', 20);
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('device_id')->constrained();
            $table->foreignId('returned_device_id')->nullable()->constrained('devices');
            $table->foreignId('technician_id')->nullable()->constrained('users');
            $table->text('fault_description');
            $table->string('accessories')->nullable();
            $table->date('received_date')->index();
            $table->date('returned_date')->nullable()->index();
            $table->string('status', 20)->index();
            $table->string('result', 30)->nullable();
            $table->string('scrap_reason', 1000)->nullable();
            $table->unsignedTinyInteger('repair_warranty_months')->nullable();
            $table->string('quote_status', 20)->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('quote_decided_at')->nullable();
            $table->boolean('is_chargeable')->default(false);
            $table->decimal('charge_amount', 15, 0)->nullable();
            $table->string('erp_receipt_no', 50)->nullable()->index();
            $table->string('erp_return_no', 50)->nullable()->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rma_tickets');
        Schema::dropIfExists('ticket_sequences');
    }
};
