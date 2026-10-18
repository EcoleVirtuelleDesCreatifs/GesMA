<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Comptes utilisateurs et profils (Direction) */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('store')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->integer('store_id')))
            ->orderBy('name')->paginate(40)->withQueryString();

        return view('users.index', ['users' => $users, 'stores' => Store::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['is_active' => true, 'role' => 'vendeur']), 'stores' => Store::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        ActivityLog::record('utilisateur.creation', "Création du compte {$user->name} ({$user->roleLabel()})", $user, [], $user->store_id);

        return redirect()->route('users.index')->with('success', 'Compte créé.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'stores' => Store::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        // On ne peut pas retirer ses propres droits de Direction ni se désactiver
        if ($user->is($request->user()) && ($data['role'] !== 'direction' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'Vous ne pouvez pas retirer vos propres droits de Direction.']);
        }

        $before = $user->only('role', 'store_id', 'is_active', 'email');
        $user->update($data);
        ActivityLog::record('utilisateur.modification', "Modification du compte {$user->name}", $user, [
            'avant' => $before,
            'apres' => $user->only(array_keys($before)),
            'mot_de_passe_modifie' => isset($data['password']),
        ], $user->store_id);

        return redirect()->route('users.index')->with('success', 'Compte mis à jour.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(config('gesma.roles')))],
            'store_id' => [Rule::requiredIf(fn () => $request->input('role') !== 'direction'), 'nullable', 'exists:stores,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['store_id.required' => 'Un magasin est obligatoire pour ce profil.']);

        $data['is_active'] = $request->boolean('is_active');
        if ($data['role'] === 'direction' && empty($data['store_id'])) {
            $data['store_id'] = null;
        }
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        return $data;
    }
}
