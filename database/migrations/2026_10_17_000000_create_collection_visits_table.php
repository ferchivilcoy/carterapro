<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('installments')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('type', 20)->comment('call, whatsapp, visit, message');
            $table->string('result', 30)->comment('promised_to_pay, no_answer, wrong_number, complaint, payment_reported, no_interest, contact_successful');
            $table->date('visited_at')->default(Carbon::today()->toDateString());
            $table->time('visited_at_time')->nullable();
            $table->date('commitment_date')->nullable();
            $table->decimal('commitment_amount', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('managed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'visited_at']);
            $table->index(['result']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_visits');
    }
};
