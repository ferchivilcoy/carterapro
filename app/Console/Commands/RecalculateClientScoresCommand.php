<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientScoreService;
use Illuminate\Console\Command;

class RecalculateClientScoresCommand extends Command
{
    protected $signature = 'clients:recalc-score';

    protected $description = 'Recompute the stored score and level of every client from its payment history';

    public function handle(ClientScoreService $scoreService): int
    {
        $updated = 0;

        Client::query()->each(function (Client $client) use ($scoreService, &$updated) {
            $scoreService->update($client);
            $updated++;
        });

        $this->components->info(sprintf('%d cliente(s) actualizado(s).', $updated));

        return self::SUCCESS;
    }
}
