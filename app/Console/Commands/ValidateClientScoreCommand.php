<?php

namespace App\Console\Commands;

use App\Services\ScoreHistoryValidator;
use Illuminate\Console\Command;

class ValidateClientScoreCommand extends Command
{
    protected $signature = 'clients:validate-score';

    protected $description = 'Valida el scoring interno contra el historial: tasa de incumplimiento por nivel de score';

    public function handle(ScoreHistoryValidator $validator): int
    {
        $rows = $validator->validate();

        if ($rows === []) {
            $this->components->warn('No hay clientes con score para validar.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Validación del scoring contra el historial', '-');

        $headers = ['Nivel', 'Clientes', 'Incumplieron', 'Tasa incumplimiento', 'Score promedio'];
        $rowsForTable = collect($rows)->map(fn (array $row) => [
            $row['tier'],
            $row['clients'],
            $row['defaulted'],
            $row['default_rate'].'%',
            $row['avg_score'],
        ])->all();

        $this->table($headers, $rowsForTable);

        $first = reset($rows);
        $last = end($rows);

        $this->components->info(
            'El scoring es predictivo cuando la tasa de incumplimiento baja a '
            .'medida que sube el nivel ('.$first['tier'].' = '.$first['default_rate'].'%, '
            .$last['tier'].' = '.$last['default_rate'].'%).'
        );

        return self::SUCCESS;
    }
}
