<?php

use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->actingAs(User::factory()->create())->get('/');

    $response->assertStatus(200);
});
