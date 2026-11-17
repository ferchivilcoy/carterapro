<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('income, expense');
            $table->decimal('amount', 14, 2);
            $table->string('concept', 255);
            $table->date('date')->default(Carbon::today()->toDateString());
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open')->comment('open, closed');
            $table->timestamps();

            $table->index(['type', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
