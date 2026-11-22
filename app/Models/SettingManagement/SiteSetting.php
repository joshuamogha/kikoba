<?php

namespace App\Models\SettingManagement;

use Database\Factories\SettingManagement\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * The platform's site settings: one row, managed by the platform admin. Anything left empty falls back to its default.
 */
#[Fillable(['name', 'short_name', 'motto', 'description', 'logo_path', 'favicon_path', 'footer_text', 'copyright_holder', 'support_email', 'support_phone', 'whatsapp', 'address', 'website', 'facebook_url', 'instagram_url', 'x_url', 'youtube_url', 'linkedin_url', 'brand_primary', 'brand_secondary', 'brand_accent', 'brand_background', 'brand_text', 'seo_title', 'seo_description', 'seo_keywords', 'og_image_path', 'noindex_dashboard', 'maintenance_banner', 'terms_url', 'privacy_url'])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    public const CACHE_KEY = 'site_settings';

    /**
     * The settings that hold an uploaded file (a path on the public disk).
     *
     * @var array<int, string>
     */
    public const FILES = ['logo_path', 'favicon_path', 'og_image_path'];

    /**
     * The brand colours and their defaults.
     *
     * @var array<string, string>
     */
    public const COLORS = [
        'brand_primary' => '#173d32',
        'brand_secondary' => '#537454',
        'brand_accent' => '#dfeaa5',
        'brand_background' => '#f7f6ef',
        'brand_text' => '#173d32',
    ];

    /**
     * Keep the cached copy in step with the database.
     */
    protected static function booted(): void
    {
        static::saved(fn () => static::forget());
        static::deleted(fn () => static::forget());
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['noindex_dashboard' => 'boolean'];
    }

    /**
     * The site's settings (cached). Always returns a model, with defaults for whatever has not been saved.
     */
    public static function current(): self
    {
        try {
            $attributes = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()->first()?->getAttributes() ?? []);
        } catch (QueryException) {
            // The table does not exist yet (before the first migration): use the defaults.
            $attributes = [];
        }

        return (new self)->forceFill($attributes);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * What a setting is when it has not been saved.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            ...self::COLORS,
            'name' => config('app.name'),
            'description' => 'Run your savings group, VICOBA or SACCOS in one place: members, monthly contributions, shares, loans, financial cycles and statements you can share with members.',
            'noindex_dashboard' => true,
        ];
    }

    /**
     * A setting's value, or its default when it is empty.
     */
    public function get(string $key): mixed
    {
        $value = $this->getAttribute($key);

        return $value === null || $value === '' ? (self::defaults()[$key] ?? null) : $value;
    }

    /**
     * The public URL of an uploaded file setting, or null.
     */
    public function fileUrl(string $key): ?string
    {
        $path = $this->getAttribute($key);

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * The title of a search result: the SEO title, or the name with the motto.
     */
    public function seoTitle(): string
    {
        return $this->get('seo_title') ?? trim($this->get('name').($this->motto ? ' · '.$this->motto : ' · Savings group, SACCOS and VICOBA management'));
    }

    /**
     * The meta description: the SEO description, or the site description.
     */
    public function seoDescription(): string
    {
        return $this->get('seo_description') ?? $this->get('description');
    }
}
