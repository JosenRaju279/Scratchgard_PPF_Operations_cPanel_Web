<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $portals = collect(config('portals', []));
        return view('home', [
            'portals' => $portals,
            'currentUser' => $request->user(),
        ]);
    }
}
