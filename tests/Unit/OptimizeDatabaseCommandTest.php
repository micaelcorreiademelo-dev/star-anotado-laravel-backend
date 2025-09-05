<?php

namespace Tests\Unit;

use App\Console\Commands\OptimizeDatabase;
use App\Services\DatabaseOptimizationService;
use App\Services\LoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Mockery;

class OptimizeDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock do LoggingService
        $this->app->bind(LoggingService::class, function () {
            return Mockery::mock(LoggingService::class, [
                'logUserActivity' => null,
                'logError' => null
            ]);
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_run_database_analysis_only()
    {
        // Mock do DatabaseOptimizationService
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--analyze' => true]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Iniciando análise', Artisan::output());
        $this->assertStringContainsString('Otimização concluída', Artisan::output());
    }

    /** @test */
    public function it_can_create_automatic_indexes()
    {
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([
            [
                'index_name' => 'idx_users_email',
                'table_name' => 'users',
                'column_name' => 'email'
            ]
        ]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        $mockService->shouldReceive('createRecommendedIndexes')->andReturn(['success' => true]);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--auto-index' => true]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Criando índices', Artisan::output());
        $this->assertStringContainsString('1 índices criados', Artisan::output());
    }

    /** @test */
    public function it_can_perform_vacuum_operation()
    {
        // Mock das tabelas do banco
        DB::shouldReceive('select')
            ->with(Mockery::pattern('/SELECT tablename.*FROM pg_tables/i'))
            ->andReturn([
                (object)['tablename' => 'users'],
                (object)['tablename' => 'posts']
            ]);
        
        DB::shouldReceive('statement')
            ->with('VACUUM ANALYZE users')
            ->andReturn(true);
        
        DB::shouldReceive('statement')
            ->with('VACUUM ANALYZE posts')
            ->andReturn(true);

        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--vacuum' => true]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Executando VACUUM', Artisan::output());
        $this->assertStringContainsString('VACUUM concluído', Artisan::output());
    }

    /** @test */
    public function it_can_optimize_specific_table()
    {
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        $mockService->shouldReceive('optimizeTable')
            ->with('users')
            ->andReturn(['status' => 'optimized']);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--table' => 'users']);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Otimizando tabela: users', Artisan::output());
        $this->assertStringContainsString('Tabela users otimizada', Artisan::output());
    }

    /** @test */
    public function it_can_generate_json_report()
    {
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--report' => 'json']);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Gerando relatório em formato json', Artisan::output());
        $this->assertStringContainsString('Relatório salvo', Artisan::output());
    }

    /** @test */
    public function it_can_generate_csv_report()
    {
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([
            ['query' => 'SELECT * FROM users', 'avg_time' => '100ms', 'calls' => 50]
        ]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([
            ['table_name' => 'users', 'column_name' => 'email', 'recommendation' => 'Create index']
        ]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([
            ['table_name' => 'users', 'size' => '10MB', 'rows' => 1000]
        ]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--report' => 'csv']);
        
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Gerando relatório em formato csv', Artisan::output());
        $this->assertStringContainsString('Relatório salvo', Artisan::output());
    }

    /** @test */
    public function it_handles_database_connection_failure()
    {
        // Mock falha na conexão
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \Exception('Connection failed'));

        $exitCode = Artisan::call('db:optimize');
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Falha na conexão', Artisan::output());
    }

    /** @test */
    public function it_handles_optimization_service_errors()
    {
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')
            ->andThrow(new \Exception('Service error'));
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize');
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Erro durante a otimização', Artisan::output());
    }

    /** @test */
    public function it_displays_comprehensive_summary()
    {
        $mockService = Mockery::mock(DatabaseOptimizationService::class);
        $mockService->shouldReceive('getSlowQueries')->andReturn([
            ['query' => 'SELECT * FROM users WHERE email = ?', 'avg_time' => '150ms']
        ]);
        $mockService->shouldReceive('getMissingIndexes')->andReturn([
            ['index_name' => 'idx_users_email', 'table_name' => 'users']
        ]);
        $mockService->shouldReceive('getTableStatistics')->andReturn([
            ['table_name' => 'users', 'size' => '10MB'],
            ['table_name' => 'posts', 'size' => '5MB']
        ]);
        $mockService->shouldReceive('getConnectionStats')->andReturn([]);
        $mockService->shouldReceive('getDatabaseSize')->andReturn(['total_size' => '100MB']);
        $mockService->shouldReceive('getOptimizationRecommendations')->andReturn([]);
        
        $this->app->instance(DatabaseOptimizationService::class, $mockService);

        $exitCode = Artisan::call('db:optimize', ['--analyze' => true]);
        
        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        
        $this->assertStringContainsString('RESUMO DA OTIMIZAÇÃO', $output);
        $this->assertStringContainsString('Queries lentas encontradas: 1', $output);
        $this->assertStringContainsString('Índices faltantes: 1', $output);
        $this->assertStringContainsString('Tabelas analisadas: 2', $output);
        $this->assertStringContainsString('Tamanho do banco: 100MB', $output);
    }

    /** @test */
    public function it_validates_report_format_parameter()
    {
        $exitCode = Artisan::call('db:optimize', ['--report' => 'invalid']);
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Formato de relatório inválido', Artisan::output());
    }

    /** @test */
    public function it_validates_table_exists_before_optimization()
    {
        DB::shouldReceive('getSchemaBuilder->hasTable')
            ->with('nonexistent_table')
            ->andReturn(false);

        $exitCode = Artisan::call('db:optimize', ['--table' => 'nonexistent_table']);
        
        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Tabela não encontrada', Artisan::output());
    }
}