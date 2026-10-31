<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminInviteController extends Controller
{
    public function index()
    {
        return view('admin.invites.index', ['invites' => Invite::latest()->paginate(50)]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'label' => 'nullable|string|max:100',
            'max_uses' => 'required|integer|min:1|max:10000',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        Invite::create([
            'code' => Str::upper(Str::random(10)),
            'label' => $d['label'] ?? null,
            'max_uses' => $d['max_uses'],
            'expires_at' => ! empty($d['days']) ? now()->addDays((int) $d['days']) : null,
        ]);

        return back()->with('status', 'Invite created.');
    }

    public function destroy(Invite $invite)
    {
        $invite->delete();

        return back()->with('status', 'Invite removed.');
    }
}
