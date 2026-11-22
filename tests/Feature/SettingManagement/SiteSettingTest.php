<?php

use App\Models\SettingManagement\SiteSetting;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_platform_admin' => true]);
    $this->user = User::factory()->create();
});

function siteSettingsPayload(array $overrides = []): array
{
    return ['name' => 'Kibubu Pro', 'noindex_dashboard' => '1', ...$overrides];
}

test('only platform admins can open or change the site settings', function () {
    $this->actingAs($this->admin)->get(route('site-settings.index'))->assertOk()->assertSee('<site-settings', false);
    $this->actingAs($this->admin)->getJson(route('site-settings.index'))->assertOk()->assertJsonPath('data.defaults.brand_primary', '#173d32');

    $this->actingAs($this->user)->get(route('site-settings.index'))->assertForbidden();
    $this->actingAs($this->user)->postJson(route('site-settings.update'), siteSettingsPayload())->assertForbidden();
    expect(SiteSetting::query()->count())->toBe(0);
});

test('settings are saved, validated and fall back to the defaults when empty', function () {
    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload([
        'motto' => 'Save together', 'support_email' => 'help@kibubu.test', 'brand_primary' => '#ff5500', 'seo_title' => 'Kibubu SEO',
    ]))->assertOk()->assertJsonPath('data.name', 'Kibubu Pro')->assertJsonPath('data.brand_primary', '#ff5500');

    $site = SiteSetting::current();

    expect($site->get('name'))->toBe('Kibubu Pro')
        ->and($site->get('brand_primary'))->toBe('#ff5500')
        ->and($site->get('brand_secondary'))->toBe('#537454')
        ->and($site->seoTitle())->toBe('Kibubu SEO');

    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload(['brand_primary' => '']))->assertOk();
    expect(SiteSetting::current()->get('brand_primary'))->toBe('#173d32');

    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload(['name' => '', 'brand_primary' => 'red', 'support_email' => 'nope', 'website' => 'ftp://x']))
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'brand_primary', 'support_email', 'website']);
});

test('logo and favicon are stored, replaced and removed', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload(['logo' => UploadedFile::fake()->image('logo.png', 200, 80)]))
        ->assertOk();
    $first = SiteSetting::current()->logo_path;
    Storage::disk('public')->assertExists($first);

    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload(['logo' => UploadedFile::fake()->image('new.png')]))->assertOk();
    Storage::disk('public')->assertMissing($first);

    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload(['remove_logo' => '1']))->assertOk()->assertJsonPath('data.logo_url', null);
    expect(SiteSetting::current()->logo_path)->toBeNull();

    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload(['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')]))
        ->assertUnprocessable()->assertJsonValidationErrors('logo');
});

test('the saved name, colours and SEO appear in the tab title and the page heads', function () {
    $this->actingAs($this->admin)->postJson(route('site-settings.update'), siteSettingsPayload([
        'brand_primary' => '#ff5500', 'seo_title' => 'Kibubu SEO title', 'seo_description' => 'Our SEO description', 'motto' => 'Save together',
        'support_email' => 'help@kibubu.test', 'maintenance_banner' => 'Back at noon',
    ]))->assertOk();

    $this->actingAs($this->admin)->get(route('site-settings.index'))
        ->assertSee('<title>Site Settings · Kibubu Pro</title>', false)
        ->assertSee('--bs-primary: #ff5500', false)
        ->assertSee('noindex', false)
        ->assertSee('Back at noon');

    $this->get(route('home'))
        ->assertSee('<title>Kibubu SEO title</title>', false)
        ->assertSee('content="Our SEO description"', false)
        ->assertSee('Save together')
        ->assertSee('help@kibubu.test')
        ->assertSee('--brand-primary: #ff5500', false)
        ->assertDontSee('noindex', false);

    Auth::logout();
    $this->get(route('login'))->assertSee('Kibubu Pro')->assertSee('· Kibubu Pro</title>', false);
});

test('without saved settings the site keeps its defaults', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
        ->assertSee('<title>'.config('app.name').'</title>', false)
        ->assertDontSee('AdminLTE | Dashboard');
});
