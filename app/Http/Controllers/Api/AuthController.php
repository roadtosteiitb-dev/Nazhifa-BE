<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'fullName' => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'userType' => 'in:buyer,owner,guest',
        ]);

        $user = User::create([
            'full_name' => $request->fullName,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'user_type' => $request->userType ?? 'buyer',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'fullName' => $user->full_name,
                'email'    => $user->email,
                'userType' => $user->user_type,
                'photo'    => $user->photo,
            ],
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = [
            'email'    => $request->email,
            'password' => $request->password,
        ];

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Email atau password salah'], 401);
        }

        $user = auth('api')->user();

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'fullName' => $user->full_name,
                'email'    => $user->email,
                'userType' => $user->user_type,
                'photo'    => $user->photo,
            ],
        ]);
    }

    // GET /api/auth/me
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        return response()->json([
            'id'       => $user->id,
            'fullName' => $user->full_name,
            'email'    => $user->email,
            'userType' => $user->user_type,
            'photo'    => $user->photo,
        ]);
    }

    // POST /api/auth/logout
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return response()->json(['message' => 'Logout berhasil']);
    }
}
