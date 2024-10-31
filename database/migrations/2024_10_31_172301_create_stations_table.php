<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->json('routes')->nullable();
            $table->unsignedBigInteger('opposite_station_id')->nullable();
            $table->boolean('is_direction_to_center')->nullable();
            $table->timestamps();

            $table->foreign('opposite_station_id')
                ->references('id')
                ->on('stations')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
