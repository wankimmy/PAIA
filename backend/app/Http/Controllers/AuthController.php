<?php

namespace App\Http\Controllers;

use App\Models\LoginOtp;
use App\Models\User;
use App\Services\PhpMailerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function requestOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $email = strtolower(trim($request->email));
            
            // Check if email is authorized
            $authorizedEmail = strtolower(config('security.authorized_email', config('app.authorized_email', 'putrafyp@gmail.com')));
            if ($email !== $authorizedEmail) {
                \Log::warning('Unauthorized OTP request attempt', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                // Return generic message to prevent email enumeration
                return response()->json([
                    'message' => 'If the email exists, an OTP has been sent.',
                ], 200);
            }
            
            // Check database connection
            try {
                \DB::connection()->getPdo();
            } catch (\Exception $e) {
                \Log::error('Database connection failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'error' => 'Database connection failed. Please check your database configuration.',
                ], 500);
            }

            $user = User::firstOrCreate(['email' => $email]);

            // Generate 6-digit OTP
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Expires in 10 minutes
            $expiresAt = now()->addMinutes(10);

            LoginOtp::create([
                'user_id' => $user->id,
                'code' => $code,
                'expires_at' => $expiresAt,
            ]);

            // Send OTP via email using PHPMailer
            try {
                $mailer = app(PhpMailerService::class);
                $mailer->sendOtp($email, $code);
            } catch (\Exception $e) {
                // Log the error but don't fail the request
                \Log::warning('Failed to send OTP email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'code' => $code, // Log code for development
                ]);
                
                // In development, log the code but never return it in production
                if (config('app.debug')) {
                    \Log::info('OTP code (debug mode only)', ['code' => $code]);
                }
            }

            return response()->json([
                'message' => 'OTP sent to your email',
            ]);
        } catch (\Exception $e) {
            \Log::error('OTP request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'An error occurred while processing your request.',
                'message' => config('app.debug') ? $e->getMessage() : 'Please try again later.',
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = strtolower(trim($request->email));
        $code = trim($request->code);
        
        // Check if email is authorized
        $authorizedEmail = strtolower(config('security.authorized_email', config('app.authorized_email', 'putrafyp@gmail.com')));
        if ($email !== $authorizedEmail) {
            \Log::warning('Unauthorized OTP verification attempt', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // Return generic error to prevent enumeration
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            \Log::warning('OTP verification for non-existent user', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);
            
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        $otp = LoginOtp::where('user_id', $user->id)
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        // Mark OTP as used
        $otp->update(['used_at' => now()]);

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'id' => $request->user()->id,
            'email' => $request->user()->email,
        ]);
    }
}

