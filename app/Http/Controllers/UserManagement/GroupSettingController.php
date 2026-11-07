<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\UpdateGroupSettingRequest;
use App\Http\Resources\UserManagement\GroupSettingResource;
use App\Models\UserManagement\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupSettingController extends Controller
{
    /**
     * Show the settings page, or the group's settings with the lookup options as JSON.
     */
    public function show(Request $request, Group $group): View|JsonResponse
    {
        Gate::authorize('view', $group);

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.settings', [
                'group' => $group,
                'canManage' => Gate::allows('manageSettings', $group),
            ]);
        }

        return (new GroupSettingResource($group->ensureGroupSetting()))->response()->setStatusCode(200);
    }

    /**
     * Update the group's settings.
     */
    public function update(UpdateGroupSettingRequest $request, Group $group): JsonResponse
    {
        $setting = $group->ensureGroupSetting();
        $setting->update($request->validated());
        $setting->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully.',
            'data' => (new GroupSettingResource($setting))->resolve(),
        ]);
    }
}
