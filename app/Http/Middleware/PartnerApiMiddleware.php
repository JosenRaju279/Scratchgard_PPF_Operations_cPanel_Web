<?php
namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PartnerApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $id=$request->header('X-Scratchgard-Client');$secret=$request->header('X-Scratchgard-Secret');
        if(!$id||!$secret)return response()->json(['message'=>'Missing partner credentials'],401);
        $client=ApiClient::where('client_id',$id)->where('active',true)->first();
        if(!$client||($client->expires_at&&$client->expires_at->isPast())||!Hash::check($secret,$client->secret_hash))return response()->json(['message'=>'Invalid or expired partner credentials'],401);
        $ips=$client->allowed_ips ?: [];if($ips && !in_array($request->ip(),$ips,true))return response()->json(['message'=>'Source IP is not allowed for this client'],403);
        $client->update(['last_used_at'=>now()]);$request->attributes->set('api_client',$client);
        return $next($request);
    }
}
