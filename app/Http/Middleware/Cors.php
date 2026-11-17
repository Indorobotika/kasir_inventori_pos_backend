<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Mengambil daftar origin dari .env dan mengubahnya menjadi array
        // Menggunakan default localhost:8080 jika variabel di .env kosong
        $envOrigins = env('FRONTEND_URL', 'http://localhost:8080');
        $allowedOrigins = array_map('trim', explode(',', $envOrigins));

        $origin = $request->headers->get('Origin');
        
        // Jika origin request cocok dengan salah satu di .env, izinkan. Jika tidak, fallback ke origin pertama
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : ($allowedOrigins[0] ?? 'http://localhost:8080');

        // Menangani request preflight (OPTIONS) dari browser/Axios
        if ($request->isMethod('OPTIONS')) {
            return response()->json('{"method":"OPTIONS"}', 200, [
                'Access-Control-Allow-Origin'      => $allowOrigin,
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        $response = $next($request);

        // Menambahkan header CORS ke setiap respons API
        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}