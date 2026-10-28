<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's registered address (MST lookup, head office) can differ from where their
 * devices are actually picked up and dropped off (a branch, a warehouse). This column is
 * optional; when blank, slips fall back to the registered address (see Customer::shippingAddress()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('shipping_address', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('shipping_address');
        });
    }
};
