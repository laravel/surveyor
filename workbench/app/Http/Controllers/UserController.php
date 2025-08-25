<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // $users = User::paginate(15);

        $whatever = 'first';

        if (request()->has('name')) {
            $whatever = 'second';

            if (request()->has('nameasdfsd')) {
                $whatever = 'third';

                return view('users.empty', compact('users'));
            }
        } elseif (request()->has('asdf')) {
            $whatever = 'fourth';
        } else {
            $whatever = 1;
        }

        return view('users.index', [
            'whatever' => $whatever,
        ]);

        // if ($users->isEmpty()) {
        //     return view('users.empty', compact('users'));
        // }

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request): RedirectResponse
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|string|email|max:255|unique:users',
    //         'password' => 'required|string|min:8|confirmed',
    //     ]);

    //     $validated['password'] = bcrypt($validated['password']);

    //     User::create($validated);

    //     return redirect()->route('users.index')
    //         ->with('success', 'User created successfully.');
    // }

    /**
     * Display the specified resource.
     */
    public function show(User $user): View
    {
        return view('users.show', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, User $user): RedirectResponse
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
    //         'password' => 'nullable|string|min:8|confirmed',
    //     ]);

    //     if (!empty($validated['password'])) {
    //         $validated['password'] = bcrypt($validated['password']);
    //     } else {
    //         unset($validated['password']);
    //     }

    //     $user->update($validated);

    //     return redirect()->route('users.index')
    //         ->with('success', 'User updated successfully.');
    // }

    /**
     * Remove the specified resource from storage.
     */
    // public function destroy(User $user): RedirectResponse
    // {
    //     $user->delete();

    //     return redirect()->route('users.index')
    //         ->with('success', 'User deleted successfully.');
    // }
}
