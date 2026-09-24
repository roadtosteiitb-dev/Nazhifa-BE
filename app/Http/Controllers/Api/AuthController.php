<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Land;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'phone'    => 'nullable|string|max:30',
        ]);

        $user = User::create([
            'full_name' => $request->fullName,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'user_type' => $request->userType ?? 'buyer',
            'phone'     => $request->phone,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user'  => self::userPayload($user),
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
            'user'  => self::userPayload($user),
        ]);
    }

    // GET /api/auth/me
    public function me(): JsonResponse
    {
        return response()->json(self::userPayload(auth('api')->user()));
    }

    // POST /api/auth/logout
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return response()->json(['message' => 'Logout berhasil']);
    }

    // PUT /api/auth/profile — name / email / phone of the logged-in user
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $request->validate([
            'fullName' => 'sometimes|required|string|max:255',
            'email'    => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => 'nullable|string|max:30',
        ]);

        if ($request->has('fullName')) $user->full_name = $request->fullName;
        if ($request->has('email'))    $user->email     = $request->email;
        if ($request->has('phone'))    $user->phone     = $request->phone;
        $user->save();

        return response()->json(self::userPayload($user));
    }

    // POST /api/auth/profile/photo — multipart field "photo"
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $user = auth('api')->user();
        $old  = $user->photo;

        $path = Storage::putFile("users/{$user->id}", $request->file('photo'));
        $user->photo = Storage::url($path);
        $user->save();

        $this->deleteStoredPhoto($old);

        return response()->json(self::userPayload($user));
    }

    // DELETE /api/auth/profile/photo
    public function deletePhoto(): JsonResponse
    {
        $user = auth('api')->user();
        $old  = $user->photo;

        $user->photo = null;
        $user->save();

        $this->deleteStoredPhoto($old);

        return response()->json(self::userPayload($user));
    }

    /** Remove a previous profile photo from our storage (external URLs are left alone). */
    private function deleteStoredPhoto(?string $url): void
    {
        if (!$url) return;
        $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
        $pos = strpos($urlPath, '/users/');
        if ($pos === false) return;

        $path = substr($urlPath, $pos + 1); // "users/{id}/{file}"
        try {
            Storage::delete($path);
        } catch (\Throwable $e) {
            Log::warning('Could not delete old profile photo: ' . $e->getMessage());
        }
    }

    /** User shape returned by every auth endpoint. */
    public static function userPayload(User $user): array
    {
        return [
            'id'       => $user->id,
            'fullName' => $user->full_name,
            'email'    => $user->email,
            'userType' => $user->user_type,
            'phone'    => $user->phone,
            'photo'    => Land::fixS3Url($user->photo),
        ];
    }
}
