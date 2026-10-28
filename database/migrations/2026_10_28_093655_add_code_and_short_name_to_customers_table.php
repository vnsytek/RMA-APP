<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers mirror the three ERP customer fields (dbo.COPMA): MA001 is `code`, MA002 is `short_name`
 * and MA003 is the existing `name`. Sizes follow COPMA (char(10), char(20)).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->unique()->after('id');
            $table->string('short_name', 20)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'short_name']);
        });
    }
};
