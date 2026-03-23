<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SalesTeam;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $email = $request->email;
        $password = $request->password;

        // 🔹 1. Check Admin User
        $user = User::where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {

            $token = $user->createToken('crm_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'role' => 'admin',
                'token' => $token,
                'user' => $user
            ]);
        }

        // 🔹 2. Check Sales Team
        $sales = SalesTeam::where('email', $email)->first();

        if ($sales && Hash::check($password, $sales->password)) {

            // Optional: check active status
            if (!$sales->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Account is inactive'
                ], 403);
            }

            $token = $sales->createToken('crm_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'role' => 'sales',
                'token' => $token,
                'user' => $sales
            ]);
        }

        // ❌ Invalid
        return response()->json([
            'status' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }
}