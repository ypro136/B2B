<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a personal access token for a company portal user.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()
            ->with('company')
            ->where('email', $data['email'])
            ->first();

        $canUseApi = $user !== null
            && Hash::check($data['password'], $user->password)
            && $user->is_active
            && $user->company_id !== null
            && $user->company?->is_active === true;

        if (! $canUseApi) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'company-api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Return the authenticated company user.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Signed out.',
        ]);
    }

    /**
     * Shape the authenticated user for API responses.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['company', 'roles']);

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'company' => $user->company === null ? null : [
                'id' => $user->company->getKey(),
                'name' => $user->company->name,
                'seat_limit' => $user->company->seat_limit,
                'seats_in_use' => $user->company->confirmedSeats(),
            ],
            'roles' => $user->roles->pluck('name')->values(),
        ];
    }
}
