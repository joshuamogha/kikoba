<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the dashboard returns a successful response for a signed-in user', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertStatus(200);
});
