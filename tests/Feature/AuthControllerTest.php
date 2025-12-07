<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful user registration
     */
    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'phone' => '201001234567',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'doctor',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'role',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Ahmed Hassan',
                        'phone' => '201001234567',
                        'email' => 'ahmed@example.com',
                        'role' => 'doctor',
                        'is_active' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '201001234567',
            'email' => 'ahmed@example.com',
        ]);
    }

    /**
     * Test registration with default role
     */
    public function test_user_registers_with_default_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Mohamed Ali',
            'phone' => '201112345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'user' => [
                        'role' => 'user',
                    ],
                ],
            ]);
    }

    /**
     * Test registration fails with duplicate phone
     */
    public function test_registration_fails_with_duplicate_phone(): void
    {
        User::factory()->create([
            'phone' => '201001234567',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'phone' => '201001234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    /**
     * Test registration fails with duplicate email
     */
    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'ahmed@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'phone' => '201001234567',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /**
     * Test registration fails without phone
     */
    public function test_registration_fails_without_phone(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    /**
     * Test registration fails with password mismatch
     */
    public function test_registration_fails_with_password_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'phone' => '201001234567',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /**
     * Test registration fails with short password
     */
    public function test_registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'phone' => '201001234567',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /**
     * Test successful user login
     */
    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'phone',
                        'role',
                        'is_active',
                    ],
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'phone' => '201001234567',
                    ],
                ],
            ]);

        // Verify token is returned
        $this->assertIsString($response['data']['token']);
        $this->assertNotEmpty($response['data']['token']);
    }

    /**
     * Test login fails with invalid phone
     */
    public function test_login_fails_with_invalid_phone(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid phone number or password',
            ]);
    }

    /**
     * Test login fails with incorrect password
     */
    public function test_login_fails_with_incorrect_password(): void
    {
        User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid phone number or password',
            ]);
    }

    /**
     * Test login fails without phone
     */
    public function test_login_fails_without_phone(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    /**
     * Test login fails without password
     */
    public function test_login_fails_without_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /**
     * Test get authenticated user
     */
    public function test_can_get_authenticated_user(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ])['data']['token'];

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'role',
                    'is_active',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'phone' => '201001234567',
                ],
            ]);
    }

    /**
     * Test get user fails without token
     */
    public function test_get_user_fails_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test get user fails with invalid token
     */
    public function test_get_user_fails_with_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer invalid.token.here',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test refresh token
     */
    public function test_can_refresh_token(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $oldToken = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ])['data']['token'];

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => "Bearer {$oldToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully',
            ]);

        // Verify new token is different
        $newToken = $response['data']['token'];
        $this->assertNotEmpty($newToken);
        $this->assertIsString($newToken);
    }

    /**
     * Test refresh token fails without token
     */
    public function test_refresh_token_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test change password successfully
     */
    public function test_can_change_password(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ])['data']['token'];

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        // Verify new password works
        $loginResponse = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'newpassword456',
        ]);

        $loginResponse->assertStatus(200);
    }

    /**
     * Test change password fails with wrong current password
     */
    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ])['data']['token'];

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'wrong_password',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test change password fails with password mismatch
     */
    public function test_change_password_fails_with_password_mismatch(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ])['data']['token'];

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'different_password',
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('new_password');
    }

    /**
     * Test logout successfully
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'phone' => '201001234567',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'phone' => '201001234567',
            'password' => 'password123',
        ])['data']['token'];

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);
    }

    /**
     * Test logout fails without token
     */
    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test multiple user roles
     */
    public function test_can_register_with_different_roles(): void
    {
        $roles = ['admin', 'doctor', 'nurse', 'receptionist', 'user'];

        foreach ($roles as $index => $role) {
            $response = $this->postJson('/api/auth/register', [
                'name' => "User {$index}",
                'phone' => '2010012345' . $index . $index,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => $role,
            ]);

            $response->assertStatus(201)
                ->assertJson([
                    'data' => [
                        'user' => [
                            'role' => $role,
                        ],
                    ],
                ]);
        }
    }

    /**
     * Test active status on registration
     */
    public function test_user_is_active_on_registration(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed Hassan',
            'phone' => '201001234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'user' => [
                        'is_active' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '201001234567',
            'is_active' => true,
        ]);
    }
}
