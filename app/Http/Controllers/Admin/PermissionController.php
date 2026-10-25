<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AuditLogger;
use App\Actions\PermissionRegistry;
use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.permissions', [
            'labels' => PermissionRegistry::LABELS,
            'defaults' => PermissionRegistry::DEFAULTS,
            'overrides' => RolePermission::all()->groupBy('role'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([UserRole::WaterProvider->value, UserRole::Consumer->value])],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(array_keys(PermissionRegistry::LABELS))],
        ]);
        $selected = $data['permissions'] ?? [];

        if (array_diff($selected, PermissionRegistry::DEFAULTS[$data['role']])) {
            throw ValidationException::withMessages(['permissions' => 'This role cannot use one or more selected permissions.']);
        }

        DB::transaction(function () use ($request, $audit, $data, $selected): void {
            foreach (PermissionRegistry::DEFAULTS[$data['role']] as $permission) {
                $row = RolePermission::updateOrCreate(
                    ['role' => $data['role'], 'permission' => $permission],
                    ['allowed' => in_array($permission, $selected, true)],
                );
                $audit->record($row, 'permission.updated', $request->user(), after: ['allowed' => $row->allowed]);
            }
        });

        return back()->with('status', 'Role permissions saved.');
    }
}
