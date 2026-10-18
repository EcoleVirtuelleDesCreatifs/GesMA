<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Installation et mise à jour de GesMA sans SSH (hébergement mutualisé).
 *
 * Activer : ajouter GESMA_INSTALL_TOKEN=<au moins 16 caractères> dans .env, puis ouvrir
 * https://votre-domaine/gesma/installation.php?token=<le même jeton>.
 * Désactiver : vider GESMA_INSTALL_TOKEN dans .env une fois l'installation terminée.
 */
$root = __DIR__;

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    exit('GesMA nécessite PHP 8.2 ou plus (actuel : '.PHP_VERSION.'). Changez la version dans le panneau de l\'hébergeur.');
}
if (! is_file("$root/vendor/autoload.php")) {
    http_response_code(500);
    exit('Dossier vendor/ absent : envoyez l\'archive gesma-deploy.zip (avec vendor/) ou lancez composer install.');
}
if (! is_file("$root/.env")) {
    http_response_code(500);
    exit('Fichier .env absent : copiez .env.production.lws en .env et complétez-le.');
}

$token = '';
foreach (file("$root/.env", FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^\s*GESMA_INSTALL_TOKEN\s*=\s*(.*?)\s*$/', $line, $m)) {
        $token = trim($m[1], '"\'');
    }
}
if (strlen($token) < 16 || ! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit('Introuvable.');
}

// Les fichiers de cache envoyés depuis un autre ordinateur contiennent de mauvais chemins.
foreach (glob("$root/bootstrap/cache/*.php") ?: [] as $cached) {
    @unlink($cached);
}
foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    is_dir("$root/$dir") || @mkdir("$root/$dir", 0775, true);
}

require "$root/vendor/autoload.php";
$app = require "$root/bootstrap/app.php";
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$steps = [];
$run = function (string $label, string $command, array $params = []) use ($kernel, &$steps) {
    try {
        $code = $kernel->call($command, $params);
        $steps[] = [$label, $code === 0, trim($kernel->output())];
    } catch (Throwable $e) {
        $steps[] = [$label, false, $e->getMessage()];
    }
};

if (! config('app.key')) {
    $run('Clé de chiffrement (APP_KEY)', 'key:generate', ['--force' => true]);
    // La nouvelle clé n'est lue qu'au prochain chargement : recharger la page pour continuer.
    $steps[] = ['Rechargez cette page pour terminer l\'installation', false, ''];
} else {
    $run('Mises à jour de la base (migrations)', 'migrate', ['--force' => true]);
    $run('Magasins, comptes et catalogue de départ', 'db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $run('Nettoyage des caches', 'optimize:clear');
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
?>
<!doctype html>
<meta charset="utf-8">
<title>Installation GesMA</title>
<style>
    body { font: 15px/1.5 system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; color: #2F241D; }
    .ok { color: #1a7f37; } .ko { color: #c62828; } pre { background: #f5efe8; padding: .75rem; white-space: pre-wrap; font-size: 13px; }
</style>
<h1>Installation GesMA</h1>
<?php foreach ($steps as [$label, $ok, $output]) { ?>
    <h3 class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? '✔' : '✘' ?> <?= htmlspecialchars($label) ?></h3>
    <?php if ($output !== '') { ?><pre><?= htmlspecialchars($output) ?></pre><?php } ?>
<?php } ?>
<p>Comptes créés : direction@mobilieraddict.ci et les comptes par magasin, mot de passe initial <code>GesMA2026!</code> — à changer dès la première connexion.</p>
<p><strong>Une fois terminé, videz GESMA_INSTALL_TOKEN dans .env</strong> pour désactiver cette page.</p>
