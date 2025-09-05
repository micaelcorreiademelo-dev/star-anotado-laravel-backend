<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar banco de dados de teste
        config([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        // Executar migrations para testes
        $this->artisan('migrate:fresh');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Helper para criar usuário autenticado
     */
    protected function authenticatedUser($attributes = [])
    {
        $user = \App\Models\User::factory()->create($attributes);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        return $user;
    }

    /**
     * Helper para fazer requisições autenticadas
     */
    protected function authenticatedJson($method, $uri, $data = [], $headers = [])
    {
        $user = $this->authenticatedUser();
        return $this->json($method, $uri, $data, $headers);
    }

    /**
     * Helper para verificar estrutura de resposta de erro
     */
    protected function assertErrorResponse($response, $status = 422)
    {
        $response->assertStatus($status)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ])
                ->assertJson([
                    'success' => false
                ]);
    }

    /**
     * Helper para verificar estrutura de resposta de sucesso
     */
    protected function assertSuccessResponse($response, $status = 200)
    {
        $response->assertStatus($status)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data'
                ])
                ->assertJson([
                    'success' => true
                ]);
    }
}