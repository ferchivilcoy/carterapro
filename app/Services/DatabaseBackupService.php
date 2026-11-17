<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Genera una copia de la base según el motor configurado. En SQLite copia el
 * archivo (con su sidecar -wal si existe); en MySQL o MariaDB vuelca la base
 * con mysqldump. El nombre y la extensión del archivo acompañan al motor, y la
 * lista, la descarga y la purga entienden ambos formatos.
 */
class DatabaseBackupService
{
    public const KEEP = 14;

    /**
     * Motores con respaldo soportado. Cualquier otro (Postgres, SQL Server) se
     * rechaza en lugar de generar un archivo vacío o engañoso.
     */
    private const SUPPORTED_DRIVERS = ['sqlite', 'mysql', 'mariadb'];

    private const EXTENSIONS = ['sqlite', 'sql'];

    /**
     * Genera una copia de la base actual y devuelve el nombre del archivo.
     */
    public function run(): string
    {
        $driver = $this->driver();

        if (! in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new RuntimeException(sprintf(
                'La copia de seguridad no está soportada para el motor "%s".',
                $driver,
            ));
        }

        if (! Storage::disk('backups')->exists('')) {
            throw new RuntimeException('El disco "backups" no está configurado. Definilo en config/filesystems.php.');
        }

        $name = sprintf('backup-%s.%s', now()->format('Y-m-d_His'), $this->extension($driver));
        $target = Storage::disk('backups')->path($name);

        File::ensureDirectoryExists(dirname($target));

        if ($driver === 'sqlite') {
            $this->dumpSqlite($target);
        } else {
            $this->dumpMysql($target);
        }

        $this->prune();

        return $name;
    }

    /**
     * Copias guardadas en disco, de la más nueva a la más vieja.
     *
     * @return array<int, array{name: string, size: int, created_at: Carbon}>
     */
    public function files(): array
    {
        if (! Storage::disk('backups')->exists('')) {
            return [];
        }

        $disk = Storage::disk('backups');

        return collect($disk->files())
            ->filter(fn (string $file): bool => $this->isBackupFile($file))
            ->sortDesc()
            ->map(fn (string $file): array => [
                'name' => $file,
                'size' => $disk->size($file),
                'created_at' => Carbon::createFromTimestamp($disk->lastModified($file)),
            ])
            ->values()
            ->all();
    }

    /**
     * Ruta absoluta de una copia descargable, o null si el nombre no
     * corresponde a un respaldo válido. La comprobación de basename evita
     * que un nombre con separadores escape del directorio de backups.
     */
    public function path(string $name): ?string
    {
        if (! Storage::disk('backups')->exists('')) {
            return null;
        }

        $disk = Storage::disk('backups');

        if (basename($name) !== $name || ! $this->isBackupFile($name) || ! $disk->exists($name)) {
            return null;
        }

        return $disk->path($name);
    }

    /**
     * Copia el archivo SQLite a destino. En modo WAL las transacciones más
     * nuevas viven en el sidecar -wal, así que viaja junto a la base para que
     * una restauración no las pierda.
     */
    private function dumpSqlite(string $target): void
    {
        $source = $this->sqlitePath();

        if (! copy($source, $target)) {
            throw new RuntimeException('No se pudo generar la copia de seguridad.');
        }

        if (is_file($source.'-wal')) {
            copy($source.'-wal', $target.'-wal');
        }
    }

    /**
     * Vuelca la base MySQL/MariaDB con mysqldump. La contraseña viaja en un
     * archivo de opciones temporal (no en la línea de comandos, donde sería
     * visible para cualquier usuario del sistema) y se borra pase lo que pase.
     */
    private function dumpMysql(string $target): void
    {
        $optionFile = $this->writeCredentialsFile();

        try {
            $result = Process::timeout((int) config('backup.timeout', 300))
                ->run($this->mysqlDumpCommand($optionFile));

            if ($result->failed()) {
                throw new RuntimeException('No se pudo generar la copia de seguridad: '.trim($result->errorOutput()));
            }

            File::put($target, $result->output());
        } finally {
            File::delete($optionFile);
        }
    }

    /**
     * @return array<int, string>
     */
    private function mysqlDumpCommand(string $optionFile): array
    {
        $connection = $this->connectionConfig();

        return [
            (string) config('backup.mysql_dump_binary', 'mysqldump'),
            // --defaults-extra-file tiene que ser la primera opción.
            '--defaults-extra-file='.$optionFile,
            '--host='.$connection['host'],
            '--port='.$connection['port'],
            '--user='.$connection['username'],
            // Volcado consistente sin bloquear las tablas de la aplicación.
            '--single-transaction',
            '--skip-lock-tables',
            // Rutinas y eventos forman parte de la base y no se pierden.
            '--routines',
            '--events',
            '--no-tablespaces',
            (string) $connection['database'],
        ];
    }

    /**
     * Archivo de opciones [client] con la contraseña, para no exponerla en
     * argv. Se crea con permisos de sólo lectura para el dueño.
     */
    private function writeCredentialsFile(): string
    {
        $password = (string) ($this->connectionConfig()['password'] ?? '');

        $file = tempnam(sys_get_temp_dir(), 'mysqldump');

        if ($file === false) {
            throw new RuntimeException('No se pudo preparar el archivo temporal de credenciales.');
        }

        File::put($file, "[client]\npassword=\"".$this->escapeOptionValue($password)."\"\n");

        @chmod($file, 0600);

        return $file;
    }

    /**
     * Escapa comillas y barras invertidas para que el valor viaje intacto
     * dentro del archivo de opciones de MySQL.
     */
    private function escapeOptionValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * Conserva las self::KEEP copias más nuevas y borra el resto junto con sus
     * sidecars. Los nombres empiezan con una marca de tiempo de ancho fijo, así
     * que ordenarlos de mayor a menor los ordena cronológicamente.
     */
    private function prune(): void
    {
        if (! Storage::disk('backups')->exists('')) {
            return;
        }

        $disk = Storage::disk('backups');

        collect($disk->files())
            ->filter(fn (string $file): bool => $this->isBackupFile($file))
            ->sortDesc()
            ->slice(self::KEEP)
            ->each(function (string $file) use ($disk): void {
                $disk->delete($file);

                foreach (['-wal', '-shm'] as $suffix) {
                    if ($disk->exists($file.$suffix)) {
                        $disk->delete($file.$suffix);
                    }
                }
            });
    }

    private function isBackupFile(string $file): bool
    {
        return in_array(pathinfo($file, PATHINFO_EXTENSION), self::EXTENSIONS, true);
    }

    private function extension(string $driver): string
    {
        return $driver === 'sqlite' ? 'sqlite' : 'sql';
    }

    private function driver(): string
    {
        return (string) ($this->connectionConfig()['driver'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        return (array) config('database.connections.'.config('database.default'), []);
    }

    /**
     * Ruta del archivo SQLite de la conexión por defecto.
     */
    private function sqlitePath(): string
    {
        $path = $this->connectionConfig()['database'] ?? null;

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('No se encontró el archivo de la base SQLite para respaldar.');
        }

        return $path;
    }
}
