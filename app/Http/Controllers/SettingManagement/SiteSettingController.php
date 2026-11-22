<?php

namespace App\Http\Controllers\SettingManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingManagement\UpdateSiteSettingRequest;
use App\Models\SettingManagement\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    /**
     * Show the page, or the saved settings (with the uploaded files' URLs and the defaults) as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.setting-management.site-settings');
        }

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * Save the settings. A new file replaces the old one; a "remove" flag deletes it.
     */
    public function update(UpdateSiteSettingRequest $request): JsonResponse
    {
        $setting = SiteSetting::query()->firstOrNew();
        $data = collect($request->validated())->except(['logo', 'favicon', 'og_image', 'remove_logo', 'remove_favicon', 'remove_og_image'])->all();

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path', 'og_image' => 'og_image_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $this->deleteFile($setting->{$column});
                $data[$column] = $request->file($input)->store('site', 'public');
            } elseif ($request->boolean("remove_{$input}")) {
                $this->deleteFile($setting->{$column});
                $data[$column] = null;
            }
        }

        $setting->fill($data)->save();

        return response()->json(['success' => true, 'message' => 'Site settings saved.', 'data' => $this->payload()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $setting = SiteSetting::current();

        return [
            ...collect($setting->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(),
            'logo_url' => $setting->fileUrl('logo_path'),
            'favicon_url' => $setting->fileUrl('favicon_path'),
            'og_image_url' => $setting->fileUrl('og_image_path'),
            'defaults' => SiteSetting::defaults(),
        ];
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
