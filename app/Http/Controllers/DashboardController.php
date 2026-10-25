<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        abort_unless($user->status === 'active', 403);

        if ($user->is_super_admin) {
            return redirect()->route('admin.index');
        }

        if ($user->memberships()->where('status', 'active')->exists()) {
            return redirect()->route('provider.index');
        }

        if ($user->consumers()->where('status', 'active')->exists()) {
            return redirect()->route('consumer.index');
        }

        return view('dashboard');
    }
}
