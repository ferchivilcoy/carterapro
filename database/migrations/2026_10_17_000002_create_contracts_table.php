<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('contract_number', 50)->unique();
            $table->string('pdf_path', 255);
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('loan_id');
            $table->index('contract_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
