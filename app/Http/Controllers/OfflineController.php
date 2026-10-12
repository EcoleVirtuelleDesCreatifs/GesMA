<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/** Fichiers de l'application installable (PWA) et page de travail hors connexion. */
class OfflineController extends Controller
{
    /** Fichiers mis en cache sur l'appareil pour fonctionner sans Internet. */
    public const SHELL = [
        '/hors-ligne',
        '/css/app.css',
        '/js/app.js',
        '/js/offline-store.js',
        '/js/offline-app.js',
        '/images/logo.svg',
        '/images/icon-192.png',
        '/images/icon-512.png',
        '/manifest.webmanifest',
    ];

    public function serviceWorker(): Response
    {
        $source = file_get_contents(resource_path('js/sw.js'));
        $version = substr(md5($source.implode('|', array_map(fn ($f) => @filemtime(public_path(ltrim($f, '/'))), self::SHELL)).filemtime(resource_path('views/offline/app.blade.php'))), 0, 10);
        $script = str_replace(['__VERSION__', '__SHELL__'], [$version, json_encode(self::SHELL)], $source);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function manifest(): Response
    {
        return response(json_encode([
            'name' => 'GesMA — Mobilier Addict',
            'short_name' => 'GesMA',
            'description' => 'Gestion des commandes, ventes, stocks et livraisons de Mobilier Addict',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#FAF7F2',
            'theme_color' => config('gesma.colors.primary'),
            'lang' => 'fr',
            'icons' => [
                ['src' => '/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function app()
    {
        return response()->view('offline.app')->header('Cache-Control', 'no-cache');
    }
}
