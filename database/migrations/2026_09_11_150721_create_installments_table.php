<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('expected_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('pending_balance', 14, 2);
            $table->decimal('late_fee', 14, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index('status');
            $table->index('due_date');
            $table->index(['loan_id', 'status'], 'installments_loan_status_index');
            $table->index(['status', 'due_date'], 'installments_status_due_date_index');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE installments ADD CONSTRAINT chk_installments_status CHECK (status IN ('pending', 'partial', 'paid', 'overdue', 'cancelled'))");
        DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installments_amounts CHECK (expected_amount > 0 AND paid_amount >= 0 AND pending_balance >= 0)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installments_late_fee CHECK (late_fee >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
