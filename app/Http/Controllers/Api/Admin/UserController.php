<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'cashier'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Pengguna berhasil ditambahkan.',
            'data' => $this->userPayload($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($user)]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['admin', 'cashier'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->user()->id === $user->id && isset($data['is_active']) && ! $data['is_active']) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ], 422);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => $this->userPayload($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun sendiri.',
            ], 422);
        }

        if ($user->transactions()->exists()) {
            $user->update(['is_active' => false]);

            return response()->json([
                'message' => 'Pengguna memiliki riwayat transaksi. Akun dinonaktifkan.',
                'data' => $this->userPayload($user->fresh()),
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
