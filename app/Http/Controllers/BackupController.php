<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backup,
    ) {}

    /**
     * Generates a backup on demand from the settings screen and flashes the
     * outcome, so a failed copy is reported instead of passing unnoticed.
     */
    public function run(): RedirectResponse
    {
        try {
            $name = $this->backup->run();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('settings.index')
                ->with('backup_error', $exception->getMessage());
        }

        return redirect()
            ->route('settings.index')
            ->with('backup_success', $name);
    }

    public function download(string $name): BinaryFileResponse
    {
        $path = $this->backup->path($name);

        abort_unless($path, 404);

        return response()->download($path, $name);
    }
}
