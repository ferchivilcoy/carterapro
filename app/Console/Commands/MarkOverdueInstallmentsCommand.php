<?php

namespace App\Console\Commands;

use App\Services\OverdueInstallmentsService;
use Illuminate\Console\Command;

class MarkOverdueInstallmentsCommand extends Command
{
    protected $signature = 'loans:mark-overdue {--dry-run : Report what would change without writing}';

    protected $description = 'Mark the installments past their due date as overdue and flag their loans';

    public function handle(OverdueInstallmentsService $overdue): int
    {
        $graceDays = $overdue->graceDays();
        $dryRun = (bool) $this->option('dry-run');

        $candidates = $overdue->candidates();

        if ($candidates->isEmpty()) {
            $this->components->info('No hay cuotas vencidas para marcar.');

            if (! $dryRun) {
                // Aunque no haya cuotas nuevas que marcar, las ya vencidas
                // siguen devengando mora y el importe guardado debe seguir
                // el calendario.
                $overdue->refreshFees();
            }

            return self::SUCCESS;
        }

        $counts = $dryRun
            ? ['installments' => $candidates->count(), 'loans' => $overdue->loansWithCandidates($candidates)]
            : $overdue->flag($candidates);

        if (! $dryRun) {
            $overdue->refreshFees();
        }

        $this->components->info(sprintf(
            '%d cuota(s) %s y %d préstamo(s) marcado(s) como vencido (corte: %s, tolerancia: %d día(s)).',
            $counts['installments'],
            $dryRun ? 'marcar' : 'marcadas',
            $counts['loans'],
            today()->subDays($graceDays)->toDateString(),
            $graceDays,
        ));

        if ($dryRun) {
            $this->components->warn('Simulación: no se escribió nada.');
        }

        return self::SUCCESS;
    }
}
