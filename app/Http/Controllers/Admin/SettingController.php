<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AuditLogger;
use App\Actions\PlatformSettings;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(PlatformSettings $settings): View
    {
        $values = [];
        foreach (array_keys(PlatformSettings::DEFAULTS) as $key) {
            $values[$key] = $settings->get($key);
        }

        return view('admin.settings', compact('values'));
    }

    public function update(Request $request, PlatformSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $values = $request->validate([
            'platform_name' => ['required', 'string', 'max:120'],
            'due_days' => ['required', 'integer', 'between:0,90'],
            'grace_days' => ['required', 'integer', 'between:0,90'],
            'late_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'date_format' => ['required', Rule::in(['M j, Y', 'Y-m-d', 'd/m/Y'])],
            'email_notifications' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($values, $settings, $audit, $request): void {
            foreach ($values as $key => $value) {
                $before = $settings->get($key);
                $settings->put($key, (string) $value);
                $audit->record(SystemSetting::whereKey($key)->firstOrFail(), 'setting.updated', $request->user(), ['value' => $before], ['value' => (string) $value]);
            }
        });

        return back()->with('status', 'Global settings saved.');
    }
}
