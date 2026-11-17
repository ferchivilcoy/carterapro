<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de evento permitidos por el CHECK. Es una foto del enum
     * App\Enums\EventType al momento de crear la tabla: si el enum crece,
     * hay que agregar una migración que reemplace el constraint.
     *
     * @var list<string>
     */
    private const EVENT_TYPES = [
        'client_created',
        'client_updated',
        'loan_created',
        'loan_closed',
        'loan_cancelled',
        'loan_updated',
        'loan_refinanced',
        'refinance_requested',
        'installment_generated',
        'installment_overdue',
        'payment_registered',
        'payment_cancelled',
        'reminder_generated',
        'user_created',
        'user_updated',
        'login_succeeded',
        'login_failed',
        'logout',
        'password_reset_requested',
        'password_reset_completed',
    ];

    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('installment_id')->nullable()->constrained('installments')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete()->cascadeOnUpdate();
            $table->json('extra_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('event_type');
            $table->index('created_at');
            $table->index(['event_type', 'created_at'], 'events_type_created_at_index');
            $table->index(['client_id', 'created_at'], 'events_client_created_at_index');
            $table->index(['loan_id', 'created_at'], 'events_loan_created_at_index');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $allowedTypes = implode(', ', array_map(
            fn (string $type): string => "'{$type}'",
            self::EVENT_TYPES,
        ));

        DB::statement("ALTER TABLE events ADD CONSTRAINT chk_events_type CHECK (event_type IN ({$allowedTypes}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
