<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('keranjangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('produks')->onDelete('cascade');
            $table->integer('jumlah')->default(1);
            $table->timestamps();

            // Satu user hanya bisa punya 1 baris per produk
            $table->unique(['user_id', 'produk_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('keranjangs');
    }
};
