<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Driver\DriverLoginRequest;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login do app do motorista pelo celular do cadastro de motorista (spec 004).
 */
class DriverAuthController extends Controller
{
    public function login(DriverLoginRequest $request): JsonResponse
    {
        $matches = Driver::query()
            ->withoutGlobalScopes()
            ->where('phone', $request->phoneDigits())
            ->whereNotNull('linked_user_id')
            ->with('linkedUser')
            ->get()
            ->filter(fn (Driver $driver): bool => $driver->linkedUser?->role === UserRole::Driver
                && Hash::check($request->string('password')->toString(), $driver->linkedUser->password));

        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['phone' => __('Este celular está em mais de uma empresa. Peça ao administrador para corrigir o cadastro.')]);
        }

        $driver = $matches->first();

        if ($driver === null) {
            throw ValidationException::withMessages(['phone' => __('Celular ou senha incorretos.')]);
        }

        $deviceName = $request->string('device_name')->toString() ?: 'app';
        $token = $driver->linkedUser->createToken('driver-app:'.$deviceName, ['driver'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'driver' => ['id' => $driver->id, 'name' => $driver->name],
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
