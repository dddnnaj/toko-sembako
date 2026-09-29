<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    public function index()
    {
        $users = User::query()->where('role', 'pembeli')->select('id', 'name', 'email', 'role', 'no_hp', 'alamat', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    public function profil(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfil(Request $request)
    {
        $user = $request->user();

        $fields = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'email'  => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'no_hp'  => 'sometimes|nullable|string|max:20',
            'alamat' => 'sometimes|nullable|string',
        ]);

        $user->fill($fields);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data'    => $user,
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->role !== 'pembeli') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya akun pelanggan yang bisa dihapus melalui endpoint ini.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun pelanggan berhasil dihapus',
        ]);
    }
}
