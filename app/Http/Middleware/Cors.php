<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{
    public function handle($request, Closure $next)
    {
        $envOrigins = env(
            'FRONTEND_URL',
            'http://localhost:8080'
        );

        $allowedOrigins = array_map(
            'trim',
            explode(',', $envOrigins)
        );

        $origin = $request->headers->get('Origin');

        // Hanya izinkan origin yang terdaftar.
        $allowOrigin = in_array($origin, $allowedOrigins, true);

        // Preflight OPTIONS
        if ($request->isMethod('OPTIONS')) {

            if (!$allowOrigin) {
                return response('', 403);
            }

            return response('', 204, [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' =>
                    'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' =>
                    'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Max-Age' => '86400',
                'Vary' => 'Origin',
            ]);
        }

        $response = $next($request);

        if ($allowOrigin) {
            $response->headers->set(
                'Access-Control-Allow-Origin',
                $origin
            );

            $response->headers->set(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, PATCH, DELETE, OPTIONS'
            );

            $response->headers->set(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, X-Requested-With, Accept, Origin'
            );

            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}