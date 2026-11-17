<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('refinanced_from_id')->nullable()->constrained('loans')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('interest_rate', 6, 4);
            $table->string('rate_type', 20);
            $table->string('amortization_model', 30);
            $table->integer('installments_count');
            $table->string('frequency', 20);
            $table->date('start_date');
            $table->decimal('total_amount', 14, 2);
            $table->decimal('pending_balance', 14, 2);
            $table->string('status', 20)->default('active');
            $table->string('disbursement_method', 20)->nullable();
            $table->string('disbursement_account', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('status');
            $table->index('start_date');
            $table->index(['client_id', 'status'], 'loans_client_status_index');
            $table->index(['status', 'created_at'], 'loans_status_created_at_index');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loans_rate_type CHECK (rate_type IN ('total', 'monthly'))");
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loans_amortization CHECK (amortization_model IN ('fixed_interest', 'balance_interest', 'french'))");
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loans_frequency CHECK (frequency IN ('weekly', 'biweekly', 'monthly'))");
        DB::statement("ALTER TABLE loans ADD CONSTRAINT chk_loans_status CHECK (status IN ('active', 'paid', 'overdue', 'cancelled', 'refinanced'))");
        DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loans_amounts CHECK (principal_amount > 0 AND total_amount > 0 AND pending_balance >= 0)');
        DB::statement('ALTER TABLE loans ADD CONSTRAINT chk_loans_installments CHECK (installments_count > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
