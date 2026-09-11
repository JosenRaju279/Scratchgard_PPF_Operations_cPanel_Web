<?php
namespace App\Http\Controllers;

use App\Models\WorkEvent;

class AuditController extends Controller
{
    public function index()
    {
        $events=WorkEvent::with(['workOrder','actor'])->latest('created_at')->paginate(60);
        return view('audit.index',compact('events'));
    }
}
