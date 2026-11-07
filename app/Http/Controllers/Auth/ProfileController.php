<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the signed-in user's profile and group memberships.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $memberships = $user->groupMembers()
            ->with(['group', 'membershipType', 'groupMemberRoles.groupRole'])
            ->get();

        return view('backend.pages.profile', ['user' => $user, 'memberships' => $memberships]);
    }
}
