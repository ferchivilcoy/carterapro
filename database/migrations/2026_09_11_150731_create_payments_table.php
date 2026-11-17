<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->nullable()->constrained('installments')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('receipt_number', 30)->unique();
            $table->decimal('amount', 14, 2);
            $table->decimal('late_fee_amount', 14, 2)->default(0);
            $table->date('payment_date');
            $table->string('method', 20)->default('cash');
            $table->string('kind', 20)->default('installment');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('cancellation_reason')->nullable();
            $table->string('comprobante_path', 255)->nullable();
            $table->string('verification_status', 20)->default('verificado')->comment('pendiente, verificado, rechazado');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('payment_date');
            $table->index('status');
            $table->index(['client_id', 'payment_date'], 'payments_client_payment_date_index');
            $table->index(['loan_id', 'payment_date'], 'payments_loan_payment_date_index');
            $table->index(['status', 'payment_date'], 'payments_status_payment_date_index');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_method CHECK (method IN ('cash', 'transfer', 'other'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_status CHECK (status IN ('active', 'cancelled'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_cancellation CHECK (status = 'active' OR cancellation_reason IS NOT NULL)");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_late_fee CHECK (late_fee_amount >= 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_kind CHECK (kind IN ('installment', 'prepayment'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
