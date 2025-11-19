<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subscription = $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $request->endpoint],
            [
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
            ]
        );

        return response()->json($subscription, 201);
    }

    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $request->user()->pushSubscriptions()
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['message' => 'Unsubscribed']);
    }
}

