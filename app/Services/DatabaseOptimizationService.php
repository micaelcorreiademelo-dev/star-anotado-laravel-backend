<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Exception;

class DatabaseOptimizationService
{
    /**
     * Limites para análise de performance
     */
    private const SLOW_QUERY_THRESHOLD = 1000; // 1 segundo em ms
    private const MEMORY_USAGE_THRESHOLD = 50 * 1024 * 1024; // 50MB
    private const ROWS_EXAMINED_THRESHOLD = 10000;

    /**
     * Tabelas críticas do sistema
     */
    private const CRITICAL_TABLES = [
        'users',
        'companies',
        'orders',
        'order_items',
        'categories',
        'items',
        'coupons'
    ];

    /**
     * Analisa performance geral do banco de dados
     *
     * @return array
     */
    public function analyzePerformance(): array
    {
        try {
            $analysis = [
                'timestamp' => now()->toISOString(),
                'slow_queries' => $this->getSlowQueries(),
                'missing_indexes' => $this->findMissingIndexes(),
                'table_stats' => $this->getTableStatistics(),
                'connection_stats' => $this->getConnectionStatistics(),
                'recommendations' => []
            ];

            // Gerar recomendações baseadas na análise
            $analysis['recommendations'] = $this->generateRecommendations($analysis);

            // Cache do resultado por 30 minutos
            Cache::put('db_performance_analysis', $analysis, 1800);

            return $analysis;
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Erro na análise de performance: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Obtém queries lentas
     *
     * @param int $limit
     * @return array
     */
    public function getSlowQueries(int $limit = 20): array
    {
        try {
            // Para MySQL
            if (DB::getDriverName() === 'mysql') {
                return $this->getMySQLSlowQueries($limit);
            }
            
            // Para PostgreSQL
            if (DB::getDriverName() === 'pgsql') {
                return $this->getPostgreSQLSlowQueries($limit);
            }

            return [];
        } catch (Exception $e) {
            return [
                'error' => 'Erro ao obter queries lentas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Encontra índices faltantes
     *
     * @return array
     */
    public function findMissingIndexes(): array
    {
        try {
            $missingIndexes = [];

            foreach (self::CRITICAL_TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $tableIndexes = $this->analyzeTableIndexes($table);
                if (!empty($tableIndexes)) {
                    $missingIndexes[$table] = $tableIndexes;
                }
            }

            return $missingIndexes;
        } catch (Exception $e) {
            return [
                'error' => 'Erro ao analisar índices: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas das tabelas
     *
     * @return array
     */
    public function getTableStatistics(): array
    {
        try {
            $stats = [];

            foreach (self::CRITICAL_TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $stats[$table] = $this->getTableStats($table);
            }

            return $stats;
        } catch (Exception $e) {
            return [
                'error' => 'Erro ao obter estatísticas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas de conexão
     *
     * @return array
     */
    public function getConnectionStatistics(): array
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                return $this->getMySQLConnectionStats();
            }
            
            if (DB::getDriverName() === 'pgsql') {
                return $this->getPostgreSQLConnectionStats();
            }

            return [];
        } catch (Exception $e) {
            return [
                'error' => 'Erro ao obter estatísticas de conexão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Otimiza tabelas específicas
     *
     * @param array $tables
     * @return array
     */
    public function optimizeTables(array $tables = []): array
    {
        try {
            $tables = empty($tables) ? self::CRITICAL_TABLES : $tables;
            $results = [];

            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $results[$table] = [
                        'status' => 'skipped',
                        'reason' => 'Tabela não existe'
                    ];
                    continue;
                }

                $results[$table] = $this->optimizeTable($table);
            }

            return [
                'success' => true,
                'results' => $results,
                'timestamp' => now()->toISOString()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro na otimização: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Gera relatório de saúde do banco
     *
     * @return array
     */
    public function generateHealthReport(): array
    {
        try {
            $analysis = $this->analyzePerformance();
            
            $health = [
                'overall_score' => 0,
                'status' => 'unknown',
                'issues' => [],
                'recommendations' => $analysis['recommendations'] ?? [],
                'metrics' => [
                    'slow_queries_count' => count($analysis['slow_queries'] ?? []),
                    'missing_indexes_count' => count($analysis['missing_indexes'] ?? []),
                    'total_tables' => count($analysis['table_stats'] ?? []),
                    'critical_issues' => 0,
                    'warnings' => 0
                ],
                'timestamp' => now()->toISOString()
            ];

            // Calcular score e status
            $health = $this->calculateHealthScore($health, $analysis);

            return $health;
        } catch (Exception $e) {
            return [
                'overall_score' => 0,
                'status' => 'error',
                'error' => 'Erro ao gerar relatório: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Obtém queries lentas do MySQL
     *
     * @param int $limit
     * @return array
     */
    private function getMySQLSlowQueries(int $limit): array
    {
        try {
            $queries = DB::select("
                SELECT 
                    sql_text,
                    exec_count,
                    avg_timer_wait / 1000000000 as avg_time_seconds,
                    sum_timer_wait / 1000000000 as total_time_seconds,
                    sum_rows_examined,
                    sum_rows_sent,
                    digest_text
                FROM performance_schema.events_statements_summary_by_digest 
                WHERE avg_timer_wait > ? * 1000000000
                ORDER BY avg_timer_wait DESC 
                LIMIT ?
            ", [self::SLOW_QUERY_THRESHOLD / 1000, $limit]);

            return array_map(function ($query) {
                return [
                    'query' => $query->digest_text ?? $query->sql_text,
                    'executions' => $query->exec_count,
                    'avg_time' => round($query->avg_time_seconds, 4),
                    'total_time' => round($query->total_time_seconds, 4),
                    'rows_examined' => $query->sum_rows_examined,
                    'rows_sent' => $query->sum_rows_sent
                ];
            }, $queries);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtém queries lentas do PostgreSQL
     *
     * @param int $limit
     * @return array
     */
    private function getPostgreSQLSlowQueries(int $limit): array
    {
        try {
            // Requer pg_stat_statements extension
            $queries = DB::select("
                SELECT 
                    query,
                    calls,
                    mean_time,
                    total_time,
                    rows
                FROM pg_stat_statements 
                WHERE mean_time > ?
                ORDER BY mean_time DESC 
                LIMIT ?
            ", [self::SLOW_QUERY_THRESHOLD, $limit]);

            return array_map(function ($query) {
                return [
                    'query' => $query->query,
                    'executions' => $query->calls,
                    'avg_time' => round($query->mean_time / 1000, 4),
                    'total_time' => round($query->total_time / 1000, 4),
                    'rows' => $query->rows
                ];
            }, $queries);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Analisa índices de uma tabela
     *
     * @param string $table
     * @return array
     */
    private function analyzeTableIndexes(string $table): array
    {
        $suggestions = [];

        // Verificações específicas por tabela
        switch ($table) {
            case 'users':
                $suggestions = $this->analyzeUsersIndexes();
                break;
            case 'orders':
                $suggestions = $this->analyzeOrdersIndexes();
                break;
            case 'order_items':
                $suggestions = $this->analyzeOrderItemsIndexes();
                break;
            case 'items':
                $suggestions = $this->analyzeItemsIndexes();
                break;
            case 'categories':
                $suggestions = $this->analyzeCategoriesIndexes();
                break;
        }

        return $suggestions;
    }

    /**
     * Analisa índices da tabela users
     *
     * @return array
     */
    private function analyzeUsersIndexes(): array
    {
        $suggestions = [];
        $indexes = $this->getTableIndexes('users');

        // Verificar índice em email (único)
        if (!$this->hasIndex($indexes, 'email')) {
            $suggestions[] = [
                'type' => 'unique',
                'columns' => ['email'],
                'reason' => 'Email é usado para login e deve ser único'
            ];
        }

        // Verificar índice em phone
        if (!$this->hasIndex($indexes, 'phone')) {
            $suggestions[] = [
                'type' => 'index',
                'columns' => ['phone'],
                'reason' => 'Telefone é usado para busca de usuários'
            ];
        }

        return $suggestions;
    }

    /**
     * Analisa índices da tabela orders
     *
     * @return array
     */
    private function analyzeOrdersIndexes(): array
    {
        $suggestions = [];
        $indexes = $this->getTableIndexes('orders');

        // Verificar índice composto em user_id, status
        if (!$this->hasCompositeIndex($indexes, ['user_id', 'status'])) {
            $suggestions[] = [
                'type' => 'composite',
                'columns' => ['user_id', 'status'],
                'reason' => 'Consultas frequentes por usuário e status'
            ];
        }

        // Verificar índice em company_id
        if (!$this->hasIndex($indexes, 'company_id')) {
            $suggestions[] = [
                'type' => 'index',
                'columns' => ['company_id'],
                'reason' => 'Consultas por empresa são frequentes'
            ];
        }

        return $suggestions;
    }

    /**
     * Analisa índices da tabela order_items
     *
     * @return array
     */
    private function analyzeOrderItemsIndexes(): array
    {
        $suggestions = [];
        $indexes = $this->getTableIndexes('order_items');

        // Verificar índice em order_id
        if (!$this->hasIndex($indexes, 'order_id')) {
            $suggestions[] = [
                'type' => 'index',
                'columns' => ['order_id'],
                'reason' => 'Consultas por pedido são muito frequentes'
            ];
        }

        return $suggestions;
    }

    /**
     * Analisa índices da tabela items
     *
     * @return array
     */
    private function analyzeItemsIndexes(): array
    {
        $suggestions = [];
        $indexes = $this->getTableIndexes('items');

        // Verificar índice composto em company_id, category_id, is_active
        if (!$this->hasCompositeIndex($indexes, ['company_id', 'category_id', 'is_active'])) {
            $suggestions[] = [
                'type' => 'composite',
                'columns' => ['company_id', 'category_id', 'is_active'],
                'reason' => 'Consultas por empresa, categoria e status ativo'
            ];
        }

        return $suggestions;
    }

    /**
     * Analisa índices da tabela categories
     *
     * @return array
     */
    private function analyzeCategoriesIndexes(): array
    {
        $suggestions = [];
        $indexes = $this->getTableIndexes('categories');

        // Verificar índice composto em company_id, is_active
        if (!$this->hasCompositeIndex($indexes, ['company_id', 'is_active'])) {
            $suggestions[] = [
                'type' => 'composite',
                'columns' => ['company_id', 'is_active'],
                'reason' => 'Consultas por empresa e status ativo'
            ];
        }

        return $suggestions;
    }

    /**
     * Obtém estatísticas de uma tabela
     *
     * @param string $table
     * @return array
     */
    private function getTableStats(string $table): array
    {
        try {
            $stats = [
                'row_count' => DB::table($table)->count(),
                'size_mb' => 0,
                'indexes' => [],
                'last_updated' => null
            ];

            if (DB::getDriverName() === 'mysql') {
                $tableInfo = DB::select("
                    SELECT 
                        table_rows,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                        update_time
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() AND table_name = ?
                ", [$table]);

                if (!empty($tableInfo)) {
                    $info = $tableInfo[0];
                    $stats['estimated_rows'] = $info->table_rows;
                    $stats['size_mb'] = $info->size_mb;
                    $stats['last_updated'] = $info->update_time;
                }
            }

            $stats['indexes'] = $this->getTableIndexes($table);

            return $stats;
        } catch (Exception $e) {
            return [
                'error' => 'Erro ao obter estatísticas da tabela: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém índices de uma tabela
     *
     * @param string $table
     * @return array
     */
    private function getTableIndexes(string $table): array
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                $indexes = DB::select("SHOW INDEX FROM {$table}");
                
                $indexList = [];
                foreach ($indexes as $index) {
                    $indexList[] = [
                        'name' => $index->Key_name,
                        'column' => $index->Column_name,
                        'unique' => $index->Non_unique == 0,
                        'type' => $index->Index_type
                    ];
                }
                
                return $indexList;
            }

            return [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Verifica se existe um índice em uma coluna
     *
     * @param array $indexes
     * @param string $column
     * @return bool
     */
    private function hasIndex(array $indexes, string $column): bool
    {
        foreach ($indexes as $index) {
            if ($index['column'] === $column) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se existe um índice composto
     *
     * @param array $indexes
     * @param array $columns
     * @return bool
     */
    private function hasCompositeIndex(array $indexes, array $columns): bool
    {
        $indexGroups = [];
        
        foreach ($indexes as $index) {
            $indexGroups[$index['name']][] = $index['column'];
        }
        
        foreach ($indexGroups as $indexColumns) {
            if (array_intersect($columns, $indexColumns) === $columns) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtém estatísticas de conexão do MySQL
     *
     * @return array
     */
    private function getMySQLConnectionStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS LIKE 'Connections'");
            $threads = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");

            return [
                'total_connections' => $stats[0]->Value ?? 0,
                'active_connections' => $threads[0]->Value ?? 0,
                'max_connections' => $maxConnections[0]->Value ?? 0,
                'connection_usage_percent' => round(
                    (($threads[0]->Value ?? 0) / ($maxConnections[0]->Value ?? 1)) * 100,
                    2
                )
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtém estatísticas de conexão do PostgreSQL
     *
     * @return array
     */
    private function getPostgreSQLConnectionStats(): array
    {
        try {
            $stats = DB::select("
                SELECT 
                    count(*) as active_connections,
                    max_conn.setting::int as max_connections
                FROM pg_stat_activity, 
                     (SELECT setting FROM pg_settings WHERE name = 'max_connections') max_conn
                GROUP BY max_conn.setting
            ");

            if (!empty($stats)) {
                $stat = $stats[0];
                return [
                    'active_connections' => $stat->active_connections,
                    'max_connections' => $stat->max_connections,
                    'connection_usage_percent' => round(
                        ($stat->active_connections / $stat->max_connections) * 100,
                        2
                    )
                ];
            }

            return [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Otimiza uma tabela específica
     *
     * @param string $table
     * @return array
     */
    private function optimizeTable(string $table): array
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("OPTIMIZE TABLE {$table}");
                return [
                    'status' => 'optimized',
                    'message' => 'Tabela otimizada com sucesso'
                ];
            }

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("VACUUM ANALYZE {$table}");
                return [
                    'status' => 'vacuumed',
                    'message' => 'Tabela analisada e otimizada'
                ];
            }

            return [
                'status' => 'skipped',
                'message' => 'Otimização não suportada para este driver'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro na otimização: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gera recomendações baseadas na análise
     *
     * @param array $analysis
     * @return array
     */
    private function generateRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Recomendações para queries lentas
        if (!empty($analysis['slow_queries'])) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'Queries Lentas Detectadas',
                'description' => 'Foram encontradas ' . count($analysis['slow_queries']) . ' queries com performance ruim.',
                'action' => 'Revisar e otimizar as queries mais lentas'
            ];
        }

        // Recomendações para índices faltantes
        if (!empty($analysis['missing_indexes'])) {
            $recommendations[] = [
                'type' => 'indexing',
                'priority' => 'medium',
                'title' => 'Índices Faltantes',
                'description' => 'Algumas tabelas podem se beneficiar de índices adicionais.',
                'action' => 'Criar os índices sugeridos para melhorar a performance'
            ];
        }

        // Recomendações baseadas no uso de conexões
        $connectionStats = $analysis['connection_stats'] ?? [];
        if (isset($connectionStats['connection_usage_percent']) && 
            $connectionStats['connection_usage_percent'] > 80) {
            $recommendations[] = [
                'type' => 'connections',
                'priority' => 'high',
                'title' => 'Alto Uso de Conexões',
                'description' => 'O uso de conexões está em ' . $connectionStats['connection_usage_percent'] . '%',
                'action' => 'Considerar aumentar o limite de conexões ou otimizar o pool de conexões'
            ];
        }

        return $recommendations;
    }

    /**
     * Calcula score de saúde do banco
     *
     * @param array $health
     * @param array $analysis
     * @return array
     */
    private function calculateHealthScore(array $health, array $analysis): array
    {
        $score = 100;
        $issues = [];

        // Penalizar por queries lentas
        $slowQueriesCount = count($analysis['slow_queries'] ?? []);
        if ($slowQueriesCount > 0) {
            $penalty = min($slowQueriesCount * 5, 30);
            $score -= $penalty;
            $issues[] = "Queries lentas detectadas: {$slowQueriesCount}";
            $health['metrics']['critical_issues']++;
        }

        // Penalizar por índices faltantes
        $missingIndexesCount = count($analysis['missing_indexes'] ?? []);
        if ($missingIndexesCount > 0) {
            $penalty = min($missingIndexesCount * 3, 20);
            $score -= $penalty;
            $issues[] = "Índices faltantes: {$missingIndexesCount}";
            $health['metrics']['warnings']++;
        }

        // Penalizar por alto uso de conexões
        $connectionStats = $analysis['connection_stats'] ?? [];
        if (isset($connectionStats['connection_usage_percent'])) {
            $usage = $connectionStats['connection_usage_percent'];
            if ($usage > 90) {
                $score -= 20;
                $issues[] = "Uso crítico de conexões: {$usage}%";
                $health['metrics']['critical_issues']++;
            } elseif ($usage > 80) {
                $score -= 10;
                $issues[] = "Alto uso de conexões: {$usage}%";
                $health['metrics']['warnings']++;
            }
        }

        $health['overall_score'] = max(0, $score);
        $health['issues'] = $issues;

        // Determinar status baseado no score
        if ($score >= 90) {
            $health['status'] = 'excellent';
        } elseif ($score >= 75) {
            $health['status'] = 'good';
        } elseif ($score >= 60) {
            $health['status'] = 'fair';
        } elseif ($score >= 40) {
            $health['status'] = 'poor';
        } else {
            $health['status'] = 'critical';
        }

        return $health;
    }
}