<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim($request->email));
        $user  = User::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email atau password salah',
            ], 401);
        }

        $token = $user->createToken('pos-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user'  => $user,
                'token' => $token,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => trim($request->name),
            'email'    => strtolower(trim($request->email)),
            'password' => Hash::make($request->password),
            'role'     => 'kasir',
        ]);

        $token = $user->createToken('pos-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user'  => $user,
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $accessToken = $request->user() ? $request->user()->currentAccessToken() : null;
        if ($accessToken) {
            $accessToken->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out',
        ]);
    }
}