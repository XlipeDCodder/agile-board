<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegistrationSettingsController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        SystemSetting::set('registration_enabled', $validated['enabled']);

        $msg = $validated['enabled']
            ? 'Cadastro público habilitado.'
            : 'Cadastro público desabilitado.';

        return back()->with('success', $msg);
    }
}
