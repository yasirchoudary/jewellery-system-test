<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {

        $request->validate([
            'email' => 'nullable|email',
            'login' => 'nullable|string',
            'password' => 'required',
        ]);

        $loginInput = $request->input('email') ?? $request->input('login');
        if (! $loginInput) {
            throw ValidationException::withMessages([
                'email' => ['Please provide email or username.'],
            ]);
        }

        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $user = User::where($field, $loginInput)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('ayub-jewelry')->plainTextToken;

        app(AuditLogService::class)->log($user, 'login', 'auth', $user->id, 'User logged in');

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request)
    {
        app(AuditLogService::class)->log(
            $request->user(),
            'logout',
            'auth',
            $request->user()->id,
            'User logged out'
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->resolvedPermissions(),
        ];
    }
}
