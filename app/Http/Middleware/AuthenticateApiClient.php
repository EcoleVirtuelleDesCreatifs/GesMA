<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authentifie le site web Mobilier Addict via un jeton « Authorization: Bearer gma_… ». */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = ApiClient::findByToken($request->bearerToken() ?? $request->header('X-Api-Key'));
        if (! $client) {
            return response()->json(['message' => 'Jeton API invalide ou désactivé.'], 401);
        }

        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
