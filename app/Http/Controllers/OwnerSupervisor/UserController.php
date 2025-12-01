<?php

namespace App\Http\Controllers\OwnerSupervisor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return User::with('outlet')->get();
    }

    public function store(Request $request)
    {
        // INI YANG SALAH SEBELUMNYA! PAKAI auth('api') → NULL → 500 ERROR!
        // DIPERBAIKI: Ganti jadi $request->user() → Sanctum!
        if (!in_array($request->user()->role, ['owner', 'supervisor'])) {
            return response()->json(['message' => 'Akses ditolak. Hanya owner/supervisor!'], 403);
        }

        $request->validate([
            'username'   => 'required|string|unique:users,username|max:255',
            'password'   => 'required|string|min:6',
            'role'       => 'required|in:karyawan',
            'outlet_id'  => 'required|exists:outlets,id'
        ]);

        $user = User::create([
            'username'  => $request->username,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'outlet_id' => $request->outlet_id
        ]);

        return response()->json([
            'message' => 'Karyawan berhasil ditambahkan!',
            'data' => $user->load('outlet')
        ], 201);
    }

    public function show(User $user)
    {
        return $user->load('outlet');
    }

    public function update(Request $request, User $user)
    {
        if (!in_array($request->user()->role, ['owner', 'supervisor'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'username'   => 'sometimes|required|string|unique:users,username,'.$user->id,
            'password'   => 'sometimes|required|string|min:6',
            'role'       => 'sometimes|required|in:karyawan',
            'outlet_id'  => 'sometimes|required|exists:outlets,id'
        ]);

        if ($request->has('password')) {
            $request->merge(['password' => Hash::make($request->password)]);
        }

        $user->update($request->all());

        return response()->json([
            'message' => 'User berhasil diupdate!',
            'data' => $user->load('outlet')
        ]);
    }

    public function destroy(User $user, Request $request)
    {
        if (!in_array($request->user()->role, ['owner', 'supervisor'])) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus!']);
    }
}