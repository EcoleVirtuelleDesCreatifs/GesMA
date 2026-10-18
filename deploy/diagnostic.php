<?php

/**
 * Diagnostic de mise en ligne GesMA (hébergement mutualisé).
 *
 * 1. Copier ce fichier dans public/ sous un nom difficile à deviner (ex. public/diag-7f3k.php).
 * 2. L'ouvrir dans le navigateur : https://votre-domaine/diag-7f3k.php
 * 3. Corriger les lignes en rouge, puis SUPPRIMER le fichier.
 *
 * Il n'utilise pas Laravel : il fonctionne même quand l'application renvoie une erreur 500.
 */
$root = dirname(__DIR__);
$checks = [];

$check = function (string $label, bool $ok, string $detail = '') use (&$checks) {
    $checks[] = [$label, $ok, $detail];
};

$check('PHP 8.3 ou plus (actuel : '.PHP_VERSION.')', version_compare(PHP_VERSION, '8.3.0', '>='),
    'Choisir PHP 8.3 (ou plus) dans le panneau LWS pour ce domaine.');

foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'intl', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip'] as $ext) {
    $check("Extension PHP $ext", extension_loaded($ext), 'Activer l\'extension dans le panneau LWS.');
}

$check('Dossier vendor/ présent', is_file("$root/vendor/autoload.php"),
    'Lancer "composer install --no-dev --optimize-autoloader" ou envoyer l\'archive gesma-deploy.zip.');

$envFile = "$root/.env";
$env = [];
foreach (is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], '"\'');
    }
}
$check('Fichier .env présent', is_file($envFile), 'Copier .env.production.lws en .env et le compléter.');
$check('APP_KEY renseignée', str_starts_with((string) ($env['APP_KEY'] ?? ''), 'base64:'), 'Lancer "php artisan key:generate".');
$check('APP_DEBUG=false', strtolower((string) ($env['APP_DEBUG'] ?? '')) === 'false', 'Ne jamais laisser APP_DEBUG=true en ligne.');

foreach (['storage', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    $check("Dossier $dir accessible en écriture", is_dir("$root/$dir") && is_writable("$root/$dir"),
        'Créer le dossier et lui donner les droits 775 (ou 755) via FTP.');
}

$staleCache = array_filter(['config.php'], fn ($f) => is_file("$root/bootstrap/cache/$f"));
foreach ($staleCache as $f) {
    $cached = file_get_contents("$root/bootstrap/cache/$f");
    $check("bootstrap/cache/$f généré sur ce serveur", str_contains($cached, $root),
        'Fichier de cache venant d\'un autre ordinateur : le supprimer (il contient de mauvais chemins).');
}

$driver = $env['DB_CONNECTION'] ?? 'sqlite';
if ($driver === 'mysql' || $driver === 'mariadb') {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? ''),
            $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
        $check('Connexion MySQL', true);
        $migrated = (bool) $pdo->query("SHOW TABLES LIKE 'migrations'")->fetch();
        $check('Migrations exécutées', $migrated, 'Lancer "php artisan migrate --force" puis le ReferenceDataSeeder.');
    } catch (Throwable $e) {
        $check('Connexion MySQL', false, $e->getMessage());
    }
} else {
    $db = $env['DB_DATABASE'] ?? "$root/database/database.sqlite";
    $check("Base SQLite ($db)", is_file($db), 'En ligne, utiliser MySQL (DB_CONNECTION=mysql).');
}

$logs = glob("$root/storage/logs/*.log") ?: [];
usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$lastError = '';
if ($logs) {
    $tail = substr((string) file_get_contents($logs[0]), -20000);
    if (preg_match_all('/^\[[^\]]+\] \w+\.(ERROR|CRITICAL|EMERGENCY): .*$/m', $tail, $m)) {
        $lastError = mb_substr(end($m[0]), 0, 1500);
    }
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
?>
<!doctype html>
<meta charset="utf-8">
<title>Diagnostic GesMA</title>
<style>
    body { font: 15px/1.5 system-ui, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; color: #2F241D; }
    li { margin: .3rem 0; } .ok { color: #1a7f37; } .ko { color: #c62828; font-weight: 600; }
    small { display: block; color: #555; font-weight: normal; } pre { background: #f5efe8; padding: 1rem; white-space: pre-wrap; }
</style>
<h1>Diagnostic GesMA</h1>
<ul>
<?php foreach ($checks as [$label, $ok, $detail]) { ?>
    <li class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? '✔' : '✘' ?> <?= htmlspecialchars($label) ?>
        <?php if (! $ok && $detail) { ?><small><?= htmlspecialchars($detail) ?></small><?php } ?></li>
<?php } ?>
</ul>
<h2>Dernière erreur de storage/logs</h2>
<pre><?= $lastError !== '' ? htmlspecialchars($lastError) : 'Aucune erreur enregistrée.' ?></pre>
<p><strong>Supprimez ce fichier après usage.</strong></p>
