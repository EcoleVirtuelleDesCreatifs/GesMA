<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::with('store')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true, 'role' => Role::Sales]), 'stores' => Store::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        AuditLogger::log('user.create', sprintf('Compte créé : %s (%s)', $user->name, $user->role->label()), $user);

        return redirect()->route('users.index')->with('success', 'Compte de '.$user->name.' créé.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'stores' => Store::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is($this->user())) {
            // On ne peut pas retirer ses propres droits de direction ni se désactiver.
            $data['role'] = $user->role->value;
            $data['is_active'] = true;
        }
        $snapshot = fn (User $u) => ['role' => $u->role->value, 'store_id' => (string) $u->store_id, 'is_active' => $u->is_active ? '1' : '0'];
        $before = $snapshot($user);
        $user->update($data);
        $changes = array_diff_assoc($snapshot($user), $before);
        if ($changes || isset($data['password'])) {
            AuditLogger::log('user.update', 'Droits ou accès modifiés : '.$user->name, $user, ['changes' => $changes, 'password_changed' => isset($data['password'])]);
        }

        return redirect()->route('users.index')->with('success', 'Compte mis à jour.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(Role::class)],
            'store_id' => ['nullable', 'required_unless:role,direction', 'integer', 'exists:stores,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['store_id.required_unless' => 'Un magasin doit être attribué à ce profil.']);
        $data['is_active'] = $request->boolean('is_active');
        if ($data['role'] === Role::Direction->value) {
            $data['store_id'] = $data['store_id'] ?? null;
        }

        return $data;
    }
}
