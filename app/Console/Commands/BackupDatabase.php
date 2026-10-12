<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
            $process = Process::fromShellCommandline(
                'mysqldump --single-transaction --quick --routines -h "$H" -P "$P" -u "$U" "$D" | gzip > "$T"',
                null,
                ['H' => $db['host'], 'P' => $db['port'], 'U' => $db['username'], 'D' => $db['database'], 'T' => $target, 'MYSQL_PWD' => $db['password']]
            );
            $process->setTimeout(3600)->run();
            if (! $process->isSuccessful() || filesize($target) < 100) {
                $this->error('Échec de la sauvegarde : '.$process->getErrorOutput());

                return self::FAILURE;
            }
        } else {
            $this->error("Connexion « $connection » non prise en charge par cette commande.");

            return self::FAILURE;
        }

        $uploads = storage_path('app/public');
        if (is_dir($uploads)) {
            $archive = "$dir/gesma-files-$stamp.tar.gz";
            (new Process(['tar', '-czf', $archive, '-C', $uploads, '.']))->setTimeout(3600)->run();
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
}
