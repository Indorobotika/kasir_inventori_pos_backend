<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller {
    public function login(Request $request) {
    $request->validate(['email' => 'required|email', 'password' => 'required']);
    $user = User::where('email', $request->email)->first();
    
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['status' => 'error', 'message' => 'Email atau password salah'], 401);
    }
    
    $token = $user->createToken('pos-token')->plainTextToken;
    
    return response()->json([
        'status' => 'success', 
        'data' => [
            'user' => $user, 
            'token' => $token // Ubah dari 'api_token' menjadi 'token'
        ]
    ]);
}

    public function register(Request $request) {
        $request->validate(['name' => 'required', 'email' => 'required|email|unique:users', 'password' => 'required|min:6']);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'kasir',
        ]);
        $token = $user->createToken('pos-token')->plainTextToken;
        return response()->json(['status' => 'success', 'data' => ['user' => $user, 'api_token' => $token]]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['status' => 'success', 'message' => 'Logged out']);
    }
}