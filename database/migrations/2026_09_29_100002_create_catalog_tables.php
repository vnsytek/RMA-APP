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
        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('device_type_id')->constrained();
            $table->string('code', 100);
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'code']);
        });

        Schema::create('service_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brands')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_centers');
        Schema::dropIfExists('product_models');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('device_types');
    }
};
