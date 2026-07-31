<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterTenantController extends Controller
{
    /** Public "Create your workspace" signup form. */
    public function show()
    {
        return view('auth.register-tenant');
    }

    /** Provision a new agency (tenant + admin + defaults) and sign them in. */
    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:150',
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ], [
            'email.unique' => 'That email is already registered. Try logging in instead.',
        ]);

        [$tenant, $admin] = $provisioner->provision($data);

        Auth::login($admin, true);
        $request->session()->regenerate();

        return redirect('/dashboard')
            ->with('success', "Welcome to {$tenant->name}! Your workspace is ready — start by adding your team.");
    }
}
