<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if ($user->status !== 'active') {
                Auth::logout();
                return response()->json(['message' => 'Account inactive'], 403);
            }
            $user->update(['last_login_at' => now()]);
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'user' => $user->load('company', 'roles.permissions'),
                'token' => $token,
                'company' => $user->company,
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
        } catch (\Throwable $e) {
            // TransientToken (cookie-based Sanctum) doesn't support delete() — safe to ignore
        }
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('company', 'roles.permissions'),
            'company' => $request->user()->company,
        ]);
    }
}
