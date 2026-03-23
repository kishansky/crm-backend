<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // 🔑 Detect model type
        if ($user instanceof \App\Models\User) {
            return $next($request); // ✅ Admin allowed
        }

        return response()->json([
            'message' => 'Unauthorized (Admin only)'
        ], 403);
    }
}