<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $authController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authController = new AuthController();
    }

    /** @test */
    public function it_can_register_a_new_user()
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => $this->faker->phoneNumber,
        ];

        $response = $this->postJson('/api/register', $userData);

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
                            'created_at',
                            'updated_at'
                        ],
                        'token'
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name']
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_registration()
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function it_validates_email_uniqueness_on_registration()
    {
        $user = User::factory()->create();

        $userData = [
            'name' => $this->faker->name,
            'email' => $user->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'created_at',
                            'updated_at'
                        ],
                        'token'
                    ]
                ]);
    }

    /** @test */
    public function it_fails_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/login', $loginData);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Credenciais inválidas'
                ]);
    }

    /** @test */
    public function it_can_logout_authenticated_user()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logout realizado com sucesso'
                ]);
    }

    /** @test */
    public function it_can_get_authenticated_user_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ])
                ->assertJson([
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'email' => $user->email
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_update_user_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '+5511999999999',
            'address' => 'New Address, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01234-567'
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Perfil atualizado com sucesso'
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '+5511999999999'
        ]);
    }

    /** @test */
    public function it_can_change_user_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123')
        ]);
        Sanctum::actingAs($user);

        $passwordData = [
            'current_password' => 'oldpassword123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ];

        $response = $this->putJson('/api/change-password', $passwordData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Senha alterada com sucesso'
                ]);

        // Verificar se a nova senha funciona
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /** @test */
    public function it_fails_to_change_password_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123')
        ]);
        Sanctum::actingAs($user);

        $passwordData = [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123'
        ];

        $response = $this->putJson('/api/change-password', $passwordData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function it_requires_authentication_for_protected_routes()
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);

        $response = $this->postJson('/api/logout');
        $response->assertStatus(401);

        $response = $this->putJson('/api/profile', ['name' => 'Test']);
        $response->assertStatus(401);

        $response = $this->putJson('/api/change-password', [
            'current_password' => 'old',
            'new_password' => 'new',
            'new_password_confirmation' => 'new'
        ]);
        $response->assertStatus(401);
    }
}