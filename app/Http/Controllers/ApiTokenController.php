<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Personal API token management (Sanctum). Admin-gated — API access acts with
 * the creating user's permissions, so it's kept to admins. The plaintext token
 * is shown exactly once, right after creation.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return view('developer.api-tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
        ]);

        $token = $request->user()->createToken($data['name']);

        return redirect()->route('developer.api-tokens')
            ->with('new_token', $token->plainTextToken)
            ->with('new_token_name', $data['name']);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return redirect()->route('developer.api-tokens')->with('success', 'API token revoked.');
    }
}
