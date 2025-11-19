<?php

namespace App\Http\Controllers;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function requestOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $email = $request->email;
            
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

            // Send OTP via email (log if mail fails in development)
            try {
                Mail::raw("Your login code is: {$code}\n\nThis code will expire in 10 minutes.", function ($message) use ($email) {
                    $message->to($email)
                            ->subject('Your Login Code');
                });
            } catch (\Exception $e) {
                // Log the error but don't fail the request
                \Log::warning('Failed to send OTP email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'code' => $code, // Log code for development
                ]);
                
                // In development, return the code in the response for testing
                if (config('app.debug')) {
                    return response()->json([
                        'message' => 'OTP generated (email sending failed - check logs)',
                        'code' => $code, // Only in debug mode
                        'warning' => 'Email service not configured. Check backend logs for OTP code.',
                    ]);
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
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code.'],
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

