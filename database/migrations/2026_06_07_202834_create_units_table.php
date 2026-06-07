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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        // Seed default units
        $defaultUnits = [
            'pcs', 'kg', 'gram', 'liter', 'ml', 'lusin', 'pack', 'roll', 
            'lembar', 'meter', 'box', 'karung', 'bungkus', 'botol', 'sachet'
        ];
        
        foreach ($defaultUnits as $unit) {
            Illuminate\Support\Facades\DB::table('units')->insert([
                'name' => $unit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
