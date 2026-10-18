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
            if (! $this->mysqldump($config, $target)) {
                // Hébergement mutualisé : mysqldump ou proc_open indisponibles, export en PHP.
                $this->phpDump($target);
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

    private function mysqldump(array $config, string $target): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        try {
            $process = new Process([
                'mysqldump',
                '--single-transaction', '--quick', '--routines', '--no-tablespaces',
                '-h', $config['host'], '-P', (string) $config['port'], '-u', $config['username'],
                $config['database'],
                '--result-file='.$target,
            ], null, ['MYSQL_PWD' => (string) $config['password']], null, 600);
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        return $process->isSuccessful() && is_file($target) && filesize($target) > 0;
    }

    private function phpDump(string $target): void
    {
        $out = fopen($target, 'wb');
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        $pdo = DB::connection()->getPdo();

        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

            foreach (DB::table($table)->cursor() as $record) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $record);
                fwrite($out, "INSERT INTO `{$table}` VALUES (".implode(',', $values).");\n");
            }
            fwrite($out, "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
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
