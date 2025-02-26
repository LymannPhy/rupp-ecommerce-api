<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str; 

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Insert default provinces with UUIDs
        $provinces = [
            'Phnom Penh', 'Banteay Meanchey', 'Battambang', 'Kampong Cham', 'Kampong Chhnang',
            'Kampong Speu', 'Kampong Thom', 'Kampot', 'Kandal', 'Kep',
            'Koh Kong', 'Kratié', 'Mondulkiri', 'Oddar Meanchey', 'Pailin',
            'Preah Sihanouk', 'Preah Vihear', 'Prey Veng', 'Pursat', 'Ratanakiri',
            'Siem Reap', 'Stung Treng', 'Svay Rieng', 'Takéo', 'Tboung Khmum'
        ];

        foreach ($provinces as $province) {
            DB::table('provinces')->insert([
                'uuid' => Str::uuid(), 
                'name' => $province,
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
        Schema::dropIfExists('provinces');
    }
};
