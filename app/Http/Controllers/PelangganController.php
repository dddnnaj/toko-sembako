<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PelangganController extends Controller
{
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
}
