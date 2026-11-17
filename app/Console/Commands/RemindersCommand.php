<?php

namespace App\Console\Commands;

use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Mail\LoanDueReminderMail;
use App\Models\Event;
use App\Models\Loan;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class RemindersCommand extends Command
{
    protected $signature = 'loans:reminders';

    protected $description = 'Registra en la bitácora un recordatorio por cada préstamo con vencimientos dentro de la ventana configurada';

    public function handle(): int
    {
        $windowDays = Setting::getInt('reminder_days_before', Setting::DEFAULTS['reminder_days_before']);

        if ($windowDays <= 0) {
            $this->components->info('Recordatorios desactivados (días de anticipación en 0).');

            return self::SUCCESS;
        }

        $cutoff = today()->addDays($windowDays);
        $registered = 0;
        $toBeNotified = collect();

        $loans = Loan::query()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Overdue])
            ->whereHas('installments', fn ($query) => $query
                ->collectable()
                ->whereDate('due_date', '<=', $cutoff))
            ->with(['client', 'installments' => fn ($query) => $query
                ->collectable()
                ->whereDate('due_date', '<=', $cutoff)
                ->orderBy('due_date')
                ->orderBy('installment_number')])
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            // Idempotente por día: si el recordatorio de hoy ya está en la
            // bitácora, un segundo disparo (scheduler + request) no duplica.
            $alreadyRegisteredToday = Event::query()
                ->ofType(EventType::ReminderGenerated)
                ->where('loan_id', $loan->id)
                ->whereDate('created_at', today())
                ->exists();

            if ($alreadyRegisteredToday) {
                continue;
            }

            $due = $loan->installments;

            Event::create([
                'event_type' => EventType::ReminderGenerated,
                'client_id' => $loan->client_id,
                'loan_id' => $loan->id,
                'extra_data' => [
                    'installments' => $due->count(),
                    'next_due' => $due->first()?->due_date?->toDateString(),
                    'total' => round((float) $due->sum('pending_balance'), 2),
                    'window_days' => $windowDays,
                ],
                'created_at' => now(),
            ]);

            $registered++;

            // Sólo entra al aviso por email el préstamo cuyo recordatorio se
            // acaba de registrar: una segunda corrida del día no reenvía nada.
            if (filled($loan->client?->email)) {
                $toBeNotified->push($loan);
            }
        }

        $emailsSent = $this->sendEmailReminders($toBeNotified, $windowDays);

        $this->components->info(sprintf(
            '%d recordatorio(s) registrado(s) y %d correo(s) enviados para la ventana de %d día(s).',
            $registered,
            $emailsSent,
            $windowDays,
        ));

        return self::SUCCESS;
    }

    /**
     * Envía un único correo por cliente, agrupando todos sus préstamos con
     * vencimientos en la ventana. Si el aviso por email está desactivado en la
     * configuración (por defecto), no envía nada.
     *
     * @param  Collection<int, Loan>  $loans
     */
    private function sendEmailReminders(Collection $loans, int $windowDays): int
    {
        $enabled = Setting::getInt('reminder_email_enabled', Setting::DEFAULTS['reminder_email_enabled']);

        if ($enabled !== 1 || $loans->isEmpty()) {
            return 0;
        }

        return $loans
            ->groupBy('client_id')
            ->each(function (Collection $clientLoans) use ($windowDays): void {
                $client = $clientLoans->first()->client;

                Mail::to($client->email)->send(new LoanDueReminderMail($client, $clientLoans->values(), $windowDays));
            })
            ->count();
    }
}
