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
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $request->email;
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

        // Send OTP via email
        Mail::raw("Your login code is: {$code}\n\nThis code will expire in 10 minutes.", function ($message) use ($email) {
            $message->to($email)
                    ->subject('Your Login Code');
        });

        return response()->json([
            'message' => 'OTP sent to your email',
        ]);
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

