<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View|AnonymousResourceCollection
    {
        if ($request->expectsJson()) {
            return AdminResource::collection(User::query()->orderBy('id')->get())
                ->additional(['current_admin' => new AdminResource($request->user())]);
        }

        return view('pages.admins', ['pageTitle' => 'Kelola Admin']);
    }

    public function store(SaveAdminRequest $request): AdminResource
    {
        try {
            $admin = User::create($request->safe()->only(['name', 'username', 'email', 'password']));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Username atau email sudah digunakan.', 'email' => 'Username atau email sudah digunakan.']);
        }

        return new AdminResource($admin);
    }

    public function update(SaveAdminRequest $request, User $admin): JsonResponse
    {
        $data = $request->safe()->only(['name', 'username', 'email', 'password']);
        $passwordChanged = filled($data['password'] ?? null);

        if (! $passwordChanged) {
            unset($data['password']);
        } else {
            $data['remember_token'] = Str::random(60);
        }

        try {
            $admin->forceFill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Username atau email sudah digunakan.', 'email' => 'Username atau email sudah digunakan.']);
        }

        $invalidate = $passwordChanged && $admin->is($request->user());

        if ($invalidate) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['data' => new AdminResource($admin), 'sessionInvalidated' => $invalidate]);
    }

    public function destroy(Request $request, User $admin): JsonResponse
    {
        if ($admin->is($request->user())) {
            throw ValidationException::withMessages(['admin' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        DB::transaction(function () use ($admin): void {
            $admins = User::query()->orderBy('id')->lockForUpdate()->get(['id']);

            if ($admins->count() <= 1) {
                throw ValidationException::withMessages(['admin' => 'Minimal satu akun admin harus tetap tersedia.']);
            }

            User::query()->findOrFail($admin->id)->delete();
        });

        return response()->json(['message' => 'Akun admin dihapus.']);
    }
}
