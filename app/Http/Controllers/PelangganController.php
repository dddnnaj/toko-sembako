<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PelangganController extends Controller {
    public function profil(Request $request) {
        return response()->json($request->user());
    }
}