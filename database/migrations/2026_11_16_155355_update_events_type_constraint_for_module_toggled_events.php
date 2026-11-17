<?php

use App\Enums\EventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía el CHECK de events para el evento ModuleToggled. La lista se
     * deriva del enum al momento de desplegar, así quedan incluidos también
     * los tipos de las fases anteriores. SQLite no crea ese constraint.
     *
     * @var list<string>
     */
    private const PREVIOUS_TYPES = [
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
        'super_admin_granted',
        'super_admin_revoked',
    ];

    public function up(): void
    {
        $this->replaceConstraint(array_map(
            fn (EventType $type): string => $type->value,
            EventType::cases(),
        ));
    }

    public function down(): void
    {
        $this->replaceConstraint(self::PREVIOUS_TYPES);
    }

    /**
     * @param  list<string>  $types
     */
    private function replaceConstraint(array $types): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $constraintExists = DB::selectOne(
            'select CONSTRAINT_NAME from information_schema.TABLE_CONSTRAINTS
             where TABLE_SCHEMA = database() and TABLE_NAME = ? and CONSTRAINT_NAME = ? and CONSTRAINT_TYPE = ?',
            ['events', 'chk_events_type', 'CHECK'],
        ) !== null;

        if ($constraintExists) {
            $serverVersion = (string) DB::getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
            $keyword = str_contains($serverVersion, 'MariaDB') ? 'CONSTRAINT' : 'CHECK';

            DB::statement("ALTER TABLE events DROP {$keyword} chk_events_type");
        }

        $allowed = implode(', ', array_map(
            fn (string $type): string => "'{$type}'",
            $types,
        ));

        DB::statement("ALTER TABLE events ADD CONSTRAINT chk_events_type CHECK (event_type IN ({$allowed}))");
    }
};
