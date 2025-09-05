<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class UserModelTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_can_create_a_user()
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password123'),
            'phone' => $this->faker->phoneNumber,
        ];

        $user = User::create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($userData['name'], $user->name);
        $this->assertEquals($userData['email'], $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $user = new User();
        
        $expectedFillable = [
            'name',
            'email',
            'password',
            'phone',
            'address',
            'city',
            'state',
            'zip_code',
            'is_active'
        ];

        $this->assertEquals($expectedFillable, $user->getFillable());
    }

    /** @test */
    public function it_has_hidden_attributes()
    {
        $user = new User();
        
        $expectedHidden = [
            'password',
            'remember_token',
        ];

        $this->assertEquals($expectedHidden, $user->getHidden());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $user = new User();
        
        $expectedCasts = [
            'id' => 'int',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];

        $this->assertEquals($expectedCasts, $user->getCasts());
    }

    /** @test */
    public function it_uses_has_api_tokens_trait()
    {
        $user = new User();
        
        $this->assertContains(HasApiTokens::class, class_uses($user));
    }

    /** @test */
    public function it_can_create_tokens()
    {
        $user = User::factory()->create();
        
        $token = $user->createToken('test-token');
        
        $this->assertNotNull($token);
        $this->assertNotEmpty($token->plainTextToken);
    }

    /** @test */
    public function it_can_revoke_tokens()
    {
        $user = User::factory()->create();
        
        $token1 = $user->createToken('token1');
        $token2 = $user->createToken('token2');
        
        $this->assertCount(2, $user->tokens);
        
        $user->tokens()->delete();
        
        $user->refresh();
        $this->assertCount(0, $user->tokens);
    }

    /** @test */
    public function it_has_default_values()
    {
        $user = User::factory()->create();
        
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    /** @test */
    public function it_can_be_soft_deleted()
    {
        $user = User::factory()->create();
        
        $user->delete();
        
        $this->assertSoftDeleted($user);
        $this->assertNotNull($user->deleted_at);
    }

    /** @test */
    public function it_can_be_restored_after_soft_delete()
    {
        $user = User::factory()->create();
        
        $user->delete();
        $this->assertSoftDeleted($user);
        
        $user->restore();
        $this->assertNull($user->deleted_at);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::create([
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => Hash::make('password123'),
        ]);
    }

    /** @test */
    public function it_ensures_email_uniqueness()
    {
        $email = $this->faker->unique()->safeEmail;
        
        User::factory()->create(['email' => $email]);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::factory()->create(['email' => $email]);
    }

    /** @test */
    public function it_can_update_profile_information()
    {
        $user = User::factory()->create();
        
        $newData = [
            'name' => 'Updated Name',
            'phone' => '+5511999999999',
            'address' => 'New Address, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01234-567'
        ];
        
        $user->update($newData);
        
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('+5511999999999', $user->phone);
        $this->assertEquals('New Address, 123', $user->address);
        $this->assertEquals('São Paulo', $user->city);
        $this->assertEquals('SP', $user->state);
        $this->assertEquals('01234-567', $user->zip_code);
    }

    /** @test */
    public function it_can_deactivate_user()
    {
        $user = User::factory()->create(['is_active' => true]);
        
        $user->update(['is_active' => false]);
        
        $this->assertFalse($user->is_active);
    }

    /** @test */
    public function it_can_scope_active_users()
    {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);
        User::factory()->create(['is_active' => true]);
        
        $activeUsers = User::where('is_active', true)->get();
        
        $this->assertCount(2, $activeUsers);
        $activeUsers->each(function ($user) {
            $this->assertTrue($user->is_active);
        });
    }
}