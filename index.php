<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Vérification PHP minimale pour Laravel 12
if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'GesMA nécessite PHP 8.2 ou supérieur. Version actuelle : '.PHP_VERSION;
    exit(1);
}

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

// Si le contrôleur frontal n'est pas dans public/, on ajuste le chemin public
// (cas d'un hébergement mutualisé où le docroot pointe sur la racine du projet).
if (basename(__DIR__) !== 'public') {
    $app->usePublicPath(__DIR__);
}

$app->handleRequest(Request::capture());
