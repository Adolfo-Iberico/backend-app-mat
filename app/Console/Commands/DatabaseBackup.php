<?php

namespace App\Console\Commands;

use App\Models\InstitutionConfig;
use Illuminate\Console\Command;

/**
 * Genera un volcado MySQL y, opcionalmente, conserva solo el respaldo diario más reciente.
 */
class DatabaseBackup extends Command
{
    protected $signature = 'aulamate:backup
        {--prune : Elimina respaldos viejos (conserva el más reciente de cada día)}';

    protected $description = 'Genera un respaldo de la base de datos MySQL';

    /**
     * Ejecuta mysqldump, registra la fecha del respaldo y aplica la limpieza solicitada.
     */
    public function handle(): int
    {
        $dbConfig = config('database.connections.mysql');

        if (! $dbConfig) {
            $this->error('Configuración MySQL no encontrada.');

            return self::FAILURE;
        }

        $filename = 'backup_'.now()->format('Y-m-d_His').'.sql';
        $backupPath = storage_path('app/backups');

        if (! file_exists($backupPath)) {
            mkdir($backupPath, 0750, true);
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s %s > %s 2>&1',
            escapeshellarg($this->resolveMysqldumpBinary()),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($backupPath.'/'.$filename)
        );

        $oldPwd = getenv('MYSQL_PWD');
        putenv('MYSQL_PWD='.$password);

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($oldPwd === false) {
            putenv('MYSQL_PWD');
        } else {
            putenv('MYSQL_PWD='.$oldPwd);
        }

        if ($returnCode !== 0) {
            report('Backup failed: '.implode("\n", $output));
            $this->error('Error al generar el respaldo.');

            return self::FAILURE;
        }

        $config = InstitutionConfig::first();
        if ($config) {
            $config->update(['last_backup' => now()]);
        }

        $this->info("Respaldo creado: {$filename} (".filesize($backupPath.'/'.$filename).' bytes)');

        if ($this->option('prune')) {
            $this->pruneOldBackups($backupPath);
        }

        return self::SUCCESS;
    }

    /**
     * Elimina respaldos duplicados del mismo día y mantiene el archivo más reciente.
     */
    private function pruneOldBackups(string $backupPath): void
    {
        $files = glob($backupPath.'/backup_*.sql');

        if (empty($files)) {
            return;
        }

        $byDay = [];

        foreach ($files as $file) {
            $day = date('Y-m-d', filemtime($file));
            if (! isset($byDay[$day]) || filemtime($file) > filemtime($byDay[$day])) {
                $byDay[$day] = $file;
            }
        }

        $keep = array_values($byDay);

        foreach ($files as $file) {
            if (! in_array($file, $keep, true)) {
                unlink($file);
                $this->info('Respaldo viejo eliminado: '.basename($file));
            }
        }
    }

    /**
     * Resuelve el ejecutable mysqldump desde la configuración o el PATH del sistema.
     */
    private function resolveMysqldumpBinary(): string
    {
        $binaryPath = config('database.connections.mysql.dump.dump_binary_path', '');
        $binary = trim((string) $binaryPath) !== ''
            ? rtrim($binaryPath, '\\/').DIRECTORY_SEPARATOR.'mysqldump'
            : 'mysqldump';

        if (DIRECTORY_SEPARATOR === '\\') {
            $binary .= '.exe';
        }

        return $binary;
    }
}
