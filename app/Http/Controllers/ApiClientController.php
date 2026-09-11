<?php
namespace App\Http\Controllers;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class ApiClientController extends Controller {
    public function index(){return view('api-clients.index',['clients'=>ApiClient::latest()->get()]);}
    public function store(Request $request){$v=$request->validate(['name'=>'required|string|max:150','abilities'=>'nullable|array','allowed_ips'=>'nullable|string|max:2000','expires_at'=>'nullable|date']);$id='sg_'.Str::lower(Str::random(18));$secret=Str::random(64);ApiClient::create(['name'=>$v['name'],'client_id'=>$id,'secret_hash'=>Hash::make($secret),'abilities'=>$v['abilities'] ?? ['work.create','work.read'],'allowed_ips'=>array_values(array_filter(array_map('trim',explode(',',$v['allowed_ips'] ?? '')))),'expires_at'=>$v['expires_at'] ?? null,'created_by'=>auth()->id()]);return back()->with('api_secret',['client_id'=>$id,'secret'=>$secret])->with('status','API client created. Copy the secret now; it will not be shown again.');}
    public function toggle(ApiClient $client){$client->update(['active'=>!$client->active]);return back()->with('status','API client '.($client->active?'enabled':'disabled').'.');}
    public function destroy(ApiClient $client){$client->delete();return back()->with('status','API client revoked.');}
}
