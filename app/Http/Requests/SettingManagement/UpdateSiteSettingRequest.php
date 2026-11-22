<?php

namespace App\Http\Requests\SettingManagement;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingRequest extends FormRequest
{
    /**
     * Only platform admins manage the site settings.
     */
    public function authorize(): bool
    {
        return $this->user()->can('platform-admin');
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $color = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $url = ['nullable', 'string', 'url:http,https', 'max:255'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'motto' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'copyright_holder' => ['nullable', 'string', 'max:150'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => $url,
            'facebook_url' => $url,
            'instagram_url' => $url,
            'x_url' => $url,
            'youtube_url' => $url,
            'linkedin_url' => $url,
            'brand_primary' => $color,
            'brand_secondary' => $color,
            'brand_accent' => $color,
            'brand_background' => $color,
            'brand_text' => $color,
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
            'noindex_dashboard' => ['required', 'boolean'],
            'maintenance_banner' => ['nullable', 'string', 'max:255'],
            'terms_url' => $url,
            'privacy_url' => $url,
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp', 'max:512'],
            'og_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_og_image' => ['nullable', 'boolean'],
        ];
    }
}
