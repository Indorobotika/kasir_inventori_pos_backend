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
        // Daftar origin frontend yang diizinkan (sesuaikan jika memakai port lain)
        $allowedOrigins = [
            'http://localhost:8080',
            'http://localhost:5173',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:5173',
        ];

        $origin = $request->headers->get('Origin');
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : 'http://localhost:8080';

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