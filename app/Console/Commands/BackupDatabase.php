<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Sauvegarde de la base de données dans storage/app/backups (compressée).
 * Planifiée chaque nuit (routes/console.php). Restauration : voir README.
 */
class BackupDatabase extends Command
{
    protected $signature = 'gesma:backup {--keep= : Nombre de jours de sauvegardes à conserver}';

    protected $description = 'Sauvegarde la base de données GesMA et supprime les anciennes sauvegardes';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $stamp = now()->format('Y-m-d_His');

        if ($config['driver'] === 'sqlite') {
            $target = "{$dir}/gesma_{$stamp}.sqlite";
            // VACUUM INTO produit une copie cohérente même pendant l'utilisation
            DB::statement('VACUUM INTO ?', [$target]);
            $this->compress($target);
        } elseif (in_array($config['driver'], ['mysql', 'mariadb'], true)) {
            $target = "{$dir}/gesma_{$stamp}.sql";
            $process = new Process([
                'mysqldump',
                '--single-transaction', '--quick', '--routines', '--no-tablespaces',
                '-h', $config['host'], '-P', (string) $config['port'], '-u', $config['username'],
                $config['database'],
                '--result-file='.$target,
            ], null, ['MYSQL_PWD' => (string) $config['password']], null, 600);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->error('Échec de mysqldump : '.$process->getErrorOutput());

                return self::FAILURE;
            }
            $this->compress($target);
        } else {
            $this->error("Pilote {$config['driver']} non pris en charge par la sauvegarde automatique.");

            return self::FAILURE;
        }

        $keep = (int) ($this->option('keep') ?: config('gesma.backup.keep_days', 14));
        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < now()->subDays($keep)->getTimestamp()) {
                File::delete($file->getPathname());
            }
        }

        $this->info('Sauvegarde terminée.');

        return self::SUCCESS;
    }

    private function compress(string $path): void
    {
        $gz = gzopen($path.'.gz', 'wb6');
        $in = fopen($path, 'rb');
        while (! feof($in)) {
            gzwrite($gz, fread($in, 1024 * 512));
        }
        fclose($in);
        gzclose($gz);
        unlink($path);
        $this->line('Fichier : '.basename($path).'.gz');
    }
}
