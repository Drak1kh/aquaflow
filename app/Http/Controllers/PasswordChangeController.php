<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('pages.auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'confirmed', 'min:12']]);

        if (Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Choose a new password.']);
        }

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', 'Password updated.');
    }
}
