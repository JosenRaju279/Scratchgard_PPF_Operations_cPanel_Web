<?php
namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if (!$plain) return response()->json(['message'=>'Missing bearer token'],401);

        $token = ApiToken::with('user')->where('token_hash', hash('sha256',$plain))->first();
        if (!$token || ($token->expires_at && $token->expires_at->isPast()) || !$token->user || $token->user->status !== 'active') {
            return response()->json(['message'=>'Invalid or expired token'],401);
        }

        $token->forceFill(['last_used_at'=>now()])->save();
        auth()->setUser($token->user);
        $request->setUserResolver(fn() => $token->user);
        $request->attributes->set('api_token',$token);

        return $next($request);
    }
}
