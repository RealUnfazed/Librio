<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $r)
    {
        return view('account', ['user' => $r->user()]);
    }
}
