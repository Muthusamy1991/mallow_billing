<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Api-Key') ?: $request->bearerToken();

        if (blank($key)) {
            return response()->json([
                'message' => 'Missing merchant API key. Send X-Api-Key.',
            ], 401);
        }

        $merchant = Merchant::query()->where('api_key', $key)->first();

        if (! $merchant) {
            return response()->json([
                'message' => 'Invalid merchant API key.',
            ], 401);
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}
