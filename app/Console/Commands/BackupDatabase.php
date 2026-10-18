<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** Sauvegarde de la base de données et des fichiers envoyés (photos des produits), avec rotation. */
class BackupDatabase extends Command
{
    protected $signature = 'gesma:backup {--keep=30 : Nombre de jours de sauvegardes conservées}';

    protected $description = 'Sauvegarde la base de données GesMA et les fichiers envoyés';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Y-m-d_His');
        $connection = config('database.default');
        $db = config("database.connections.$connection");

        $target = "$dir/gesma-db-$stamp.sql.gz";
        if ($connection === 'sqlite') {
            $target = "$dir/gesma-db-$stamp.sqlite.gz";
            file_put_contents($target, gzencode(file_get_contents($db['database'])));
        } elseif (in_array($connection, ['mysql', 'mariadb'], true)) {
            if (! $this->mysqldump($db, $target)) {
                // Hébergement mutualisé : mysqldump ou proc_open indisponibles, export en PHP.
                $this->phpDump($target);
            }
        } else {
            $this->error("Connexion « $connection » non prise en charge par cette commande.");

            return self::FAILURE;
        }

        $uploads = storage_path('app/public');
        if (is_dir($uploads) && File::allFiles($uploads) !== []) {
            $this->archiveUploads($uploads, "$dir/gesma-files-$stamp");
        }

        $limit = now()->subDays((int) $this->option('keep'))->getTimestamp();
        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $limit) {
                File::delete($file->getPathname());
            }
        }

        $this->info('Sauvegarde créée : '.basename($target));

        return self::SUCCESS;
    }

    private function mysqldump(array $db, string $target): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        try {
            $process = Process::fromShellCommandline(
                'mysqldump --single-transaction --quick --routines -h "$H" -P "$P" -u "$U" "$D" | gzip > "$T"',
                null,
                ['H' => $db['host'], 'P' => $db['port'], 'U' => $db['username'], 'D' => $db['database'], 'T' => $target, 'MYSQL_PWD' => $db['password']]
            );
            $process->setTimeout(3600)->run();
        } catch (\Throwable) {
            return false;
        }

        return $process->isSuccessful() && is_file($target) && filesize($target) >= 100;
    }

    private function phpDump(string $target): void
    {
        $gz = gzopen($target, 'wb6');
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        $pdo = DB::connection()->getPdo();

        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'') as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `$table`"))[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");

            foreach (DB::table($table)->cursor() as $record) {
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $record);
                gzwrite($gz, "INSERT INTO `$table` VALUES (".implode(',', $values).");\n");
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
    }

    private function archiveUploads(string $uploads, string $base): void
    {
        if (function_exists('proc_open')) {
            $process = new Process(['tar', '-czf', "$base.tar.gz", '-C', $uploads, '.']);
            try {
                if ($process->setTimeout(3600)->run() === 0) {
                    return;
                }
            } catch (\Throwable) {
                // tar indisponible : archive zip ci-dessous.
            }
            @unlink("$base.tar.gz");
        }

        $zip = new \ZipArchive;
        $zip->open("$base.zip", \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (File::allFiles($uploads) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();
    }
}
