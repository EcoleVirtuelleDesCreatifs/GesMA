<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authentifie le site web Mobilier Addict par une clé partagée (en-tête X-API-KEY) */
class VerifyWebsiteApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('gesma.website.api_key');
        $given = (string) ($request->header('X-API-KEY') ?? $request->bearerToken() ?? '');

        if ($expected === '' || strlen($expected) < 8) {
            return response()->json(['message' => 'API du site non configurée (GESMA_WEBSITE_API_KEY).'], 503);
        }
        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Clé API invalide.'], 401);
        }

        return $next($request);
    }
}
