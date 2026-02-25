<?php

declare(strict_types=1);

use App\Enums\UserSubtype;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids members from opening users settings page', function (): void {
    $member = User::factory()->memberExternal()->create();

    $response = $this->actingAs($member)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('users.index'));

    $response->assertForbidden();
});

it('allows admins to open users settings page', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('users.index'));

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    $response->assertJsonPath('component', 'settings/users');
});

it('forbids non-super-admin admin from promoting or demoting admins', function (): void {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->patch(route('users.role.update', ['user' => $otherAdmin]), [
            'role' => 'member',
        ]);

    $response->assertForbidden();
});

it('allows super-admin to toggle member subtype', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $member = User::factory()->memberExternal()->create();

    $response = $this->actingAs($superAdmin)
        ->patch(route('users.subtype.update', ['user' => $member]), [
            'subtype' => UserSubtype::Internal->value,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
        'subtype' => UserSubtype::Internal->value,
    ]);
});
