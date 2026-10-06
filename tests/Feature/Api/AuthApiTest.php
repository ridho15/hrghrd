<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_successful_returns_token_and_user_resource(): void
    {
        $user = $this->createUser([
            'email'    => 'employee@hrg.test',
            'password' => Hash::make('Secret123!'),
            'role'     => 'employee',
            'active'   => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => 'employee@hrg.test',
            'password'    => 'Secret123!',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'abilities',
                    'user' => [
                        'id', 'name', 'email', 'role', 'active',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'Pixel 8',
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->createUser([
            'email'    => 'employee@hrg.test',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => 'employee@hrg.test',
            'password'    => 'WrongPassword',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->createUser([
            'email'    => 'inactive@hrg.test',
            'password' => Hash::make('Secret123!'),
            'active'   => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'       => 'inactive@hrg.test',
            'password'    => 'Secret123!',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    public function test_me_endpoint_returns_current_authenticated_user(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['employee']);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('Mobile App');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_device_hash_registration(): void
    {
        $user = $this->createUser(['device_hash' => null]);
        Sanctum::actingAs($user, ['employee']);

        $response = $this->postJson('/api/v1/auth/device', [
            'device_hash' => 'device_unique_uuid_12345678',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('device_unique_uuid_12345678', $user->fresh()->device_hash);
    }

    public function test_device_hash_conflict_fails(): void
    {
        $otherUser = $this->createUser(['device_hash' => 'registered_device_token_hash']);
        $currentUser = $this->createUser(['device_hash' => null]);
        Sanctum::actingAs($currentUser, ['employee']);

        $response = $this->postJson('/api/v1/auth/device', [
            'device_hash' => 'registered_device_token_hash',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_refresh_token_creates_new_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('Old Token', ['employee']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->postJson('/api/v1/auth/refresh', [
                'device_name' => 'Refreshed Device',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'token_type', 'abilities'],
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Refreshed Device',
        ]);
    }
}
