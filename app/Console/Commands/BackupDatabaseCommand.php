<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use RuntimeException;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Generate a timestamped backup copy of the database (SQLite or MySQL/MariaDB)';

    public function handle(DatabaseBackupService $backup): int
    {
        try {
            $name = $backup->run();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Copia de seguridad generada: %s', $name));

        return self::SUCCESS;
    }
}
