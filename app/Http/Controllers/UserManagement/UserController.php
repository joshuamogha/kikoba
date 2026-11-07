<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\UserManagement\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * List system users, with optional search by name, phone number or email.
     */
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        $users = User::query()
            ->withCount('groupMembers')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('backend.pages.users.index', ['users' => $users, 'search' => $search]);
    }
}
