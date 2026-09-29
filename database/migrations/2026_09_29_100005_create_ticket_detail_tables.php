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
        Schema::create('rma_center_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->foreignId('service_center_id')->constrained();
            $table->string('vendor_case_no', 50)->nullable()->index();
            $table->date('appointment_date')->nullable();
            $table->date('sent_date');
            $table->date('back_date')->nullable();
            $table->string('center_return_no', 50)->nullable();
            $table->string('outcome', 20)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['service_center_id', 'outcome']);
        });

        Schema::create('rma_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 0);
            $table->timestamps();
        });

        Schema::create('rma_replaced_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->foreignId('rma_center_shipment_id')->nullable()->constrained();
            $table->string('part_code', 50)->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('rma_ticket_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->constrained();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['rma_ticket_id', 'created_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_ticket_id')->constrained();
            $table->string('doc_type', 30);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('rma_ticket_status_logs');
        Schema::dropIfExists('rma_replaced_parts');
        Schema::dropIfExists('rma_quote_items');
        Schema::dropIfExists('rma_center_shipments');
    }
};
