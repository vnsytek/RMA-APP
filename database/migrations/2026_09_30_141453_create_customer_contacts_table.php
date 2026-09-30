<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer (company) has many contact people; each ticket keeps the one who sent that device.
     */
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
        });

        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->foreignId('customer_contact_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('contact_name')->nullable()->after('customer_contact_id');
            $table->string('contact_phone', 20)->nullable()->after('contact_name');
        });

        // The single contact stored on each customer becomes the first entry of its address book,
        // and existing tickets are stamped with it.
        foreach (DB::table('customers')->where(fn ($query) => $query->whereNotNull('contact_name')->orWhereNotNull('phone'))->get() as $customer) {
            $name = trim((string) $customer->contact_name) ?: $customer->name;
            $contactId = DB::table('customer_contacts')->insertGetId([
                'customer_id' => $customer->id,
                'name' => $name,
                'phone' => $customer->phone,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rma_tickets')->where('customer_id', $customer->id)->update([
                'customer_contact_id' => $contactId,
                'contact_name' => $name,
                'contact_phone' => $customer->phone,
            ]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('contact_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('tax_code');
        });

        Schema::table('rma_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_contact_id');
            $table->dropColumn(['contact_name', 'contact_phone']);
        });

        Schema::dropIfExists('customer_contacts');
    }
};
