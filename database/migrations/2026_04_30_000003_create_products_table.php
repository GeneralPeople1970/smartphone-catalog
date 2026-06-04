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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->index();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->text('image_url')->nullable();
            $table->string('price', 100)->nullable();
            $table->string('soc_name')->nullable();
            $table->unsignedInteger('battery_capacity')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->json('specs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
