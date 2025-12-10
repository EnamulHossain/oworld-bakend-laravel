<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    use FormatsUser;

    public function show(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'full_name' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        foreach (['username', 'full_name', 'about', 'phone'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field] ?? null;
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser($user),
        ]);
    }
}
