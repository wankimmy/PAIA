<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthorizedEmail
{
    /**
     * Handle an incoming request.
     * Only allow access to the authorized email address.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the authorized email from config
        $authorizedEmail = strtolower(config('security.authorized_email', config('app.authorized_email', 'putrafyp@gmail.com')));
        
        // For authentication endpoints, check the email in the request
        if ($request->is('api/auth/*')) {
            $email = $request->input('email');
            
            if ($email && strtolower($email) !== strtolower($authorizedEmail)) {
                \Log::warning('Unauthorized email access attempt', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                return response()->json([
                    'message' => 'Access denied.',
                ], 403);
            }
        }
        
        // For authenticated endpoints, check the user's email
        if ($request->user()) {
            $userEmail = strtolower($request->user()->email);
            $authorizedEmailLower = strtolower($authorizedEmail);
            
            if ($userEmail !== $authorizedEmailLower) {
                \Log::warning('Unauthorized user access attempt', [
                    'user_id' => $request->user()->id,
                    'email' => $request->user()->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                // Revoke all tokens for unauthorized user
                $request->user()->tokens()->delete();
                
                return response()->json([
                    'message' => 'Access denied.',
                ], 403);
            }
        }
        
        return $next($request);
    }
}

