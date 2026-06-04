<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('cashier_id')->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
