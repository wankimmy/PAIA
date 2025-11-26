<?php

namespace App\Http\Controllers;

use App\Models\PasswordEntry;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function index(Request $request)
    {
        $entries = $request->user()->passwordEntries()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'label' => $entry->label,
                    'username' => $entry->username,
                    'password' => $this->encryption->decrypt($entry->encrypted_password),
                    'notes' => $entry->encrypted_notes ? $this->encryption->decrypt($entry->encrypted_notes) : null,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];
            });

        return response()->json($entries);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:' . config('security.max_lengths.label', 255),
            'username' => 'required|string|max:' . config('security.max_lengths.username', 255),
            'password' => 'required|string|max:' . config('security.max_lengths.password', 1000),
            'notes' => 'nullable|string|max:' . config('security.max_lengths.notes', 10000),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entry = $request->user()->passwordEntries()->create([
            'label' => $request->label,
            'username' => $request->username,
            'encrypted_password' => $this->encryption->encrypt($request->password),
            'encrypted_notes' => $request->notes ? $this->encryption->encrypt($request->notes) : null,
        ]);

        return response()->json([
            'id' => $entry->id,
            'label' => $entry->label,
            'username' => $entry->username,
            'password' => $request->password,
            'notes' => $request->notes,
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $entry = $request->user()->passwordEntries()->findOrFail($id);

        return response()->json([
            'id' => $entry->id,
            'label' => $entry->label,
            'username' => $entry->username,
            'password' => $this->encryption->decrypt($entry->encrypted_password),
            'notes' => $entry->encrypted_notes ? $this->encryption->decrypt($entry->encrypted_notes) : null,
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
        ]);
    }

    public function update(Request $request, $id)
    {
        $entry = $request->user()->passwordEntries()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|required|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [];
        if ($request->has('label')) {
            $updateData['label'] = $request->label;
        }
        if ($request->has('username')) {
            $updateData['username'] = $request->username;
        }
        if ($request->has('password')) {
            $updateData['encrypted_password'] = $this->encryption->encrypt($request->password);
        }
        if ($request->has('notes')) {
            $updateData['encrypted_notes'] = $request->notes ? $this->encryption->encrypt($request->notes) : null;
        }

        $entry->update($updateData);

        return response()->json([
            'id' => $entry->id,
            'label' => $entry->label,
            'username' => $entry->username,
            'password' => $this->encryption->decrypt($entry->encrypted_password),
            'notes' => $entry->encrypted_notes ? $this->encryption->decrypt($entry->encrypted_notes) : null,
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $entry = $request->user()->passwordEntries()->findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Password entry deleted']);
    }
}

