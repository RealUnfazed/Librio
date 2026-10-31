<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Wrong email or password.']);
        }
        $r->session()->regenerate();

        return redirect()->intended(route('library'));
    }

    public function showRegister()
    {
        abort_if(config('library.registration') === 'closed', 404);
        return view('auth.register', ['mode' => config('library.registration')]);
    }

    public function register(Request $r)
    {
        $mode = config('library.registration');
        abort_if($mode === 'closed', 404);

        $data = $r->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'invite' => $mode === 'invite' ? 'required|string|max:64' : 'nullable|string|max:64',
        ]);

        $user = DB::transaction(function () use ($mode, $data) {
            $invite = null;
            if ($mode === 'invite') {
                $invite = Invite::where('code', strtoupper(trim($data['invite'])))->lockForUpdate()->first();
                if (! $invite || ! $invite->isUsable()) {
                    throw ValidationException::withMessages(['invite' => 'This invite code is invalid, expired or used up.']);
                }
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            if ($invite) {
                $invite->increment('uses');
            }
            return $user;
        });

        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('library');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
