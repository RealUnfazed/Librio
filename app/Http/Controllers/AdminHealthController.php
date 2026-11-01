<?php

namespace App\Http\Controllers;

use App\Services\LibraryDoctor;
use Illuminate\Http\Request;

class AdminHealthController extends Controller
{
    public function index(Request $r, LibraryDoctor $doctor)
    {
        return view('admin.health', ['results' => $doctor->run($r->isMethod('post'))]);
    }
}
