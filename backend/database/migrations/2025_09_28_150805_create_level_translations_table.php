<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();
            $table->unique(['level_id','locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_translations');
    }
};
