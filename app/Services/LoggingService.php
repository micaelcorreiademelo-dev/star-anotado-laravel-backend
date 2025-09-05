<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class LoggingService
{
    /**
     * Níveis de log disponíveis
     */
    private const LOG_LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    ];

    /**
     * Categorias de atividade
     */
    private const ACTIVITY_CATEGORIES = [
        'auth' => 'Autenticação',
        'user' => 'Usuário',
        'company' => 'Empresa',
        'order' => 'Pedido',
        'payment' => 'Pagamento',
        'system' => 'Sistema',
        'security' => 'Segurança',
        'api' => 'API',
        'file' => 'Arquivo',
        'whatsapp' => 'WhatsApp'
    ];

    /**
     * Registra atividade do usuário
     *
     * @param string $action
     * @param string $category
     * @param array $data
     * @param User|null $user
     * @param string $level
     * @return void
     */
    public function logUserActivity(
        string $action,
        string $category = 'user',
        array $data = [],
        ?User $user = null,
        string $level = 'info'
    ): void {
        try {
            $user = $user ?: auth()->user();
            
            $logData = [
                'timestamp' => now()->toISOString(),
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'action' => $action,
                'category' => $category,
                'category_name' => self::ACTIVITY_CATEGORIES[$category] ?? $category,
                'data' => $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
                'request_id' => $this->getRequestId()
            ];

            // Log no arquivo específico de atividades
            Log::channel('activity')->{$level}($action, $logData);

            // Armazenar em cache para consultas rápidas (últimas 100 atividades do usuário)
            if ($user) {
                $this->cacheUserActivity($user->id, $logData);
            }

            // Log crítico também vai para o canal principal
            if (in_array($level, ['emergency', 'alert', 'critical', 'error'])) {
                Log::{$level}("User Activity - {$action}", $logData);
            }
        } catch (Exception $e) {
            // Fallback para log simples se houver erro
            Log::error('Failed to log user activity', [
                'action' => $action,
                'category' => $category,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Registra performance de API
     *
     * @param Request $request
     * @param float $executionTime
     * @param int $memoryUsage
     * @param int $statusCode
     * @param array $additionalData
     * @return void
     */
    public function logApiPerformance(
        Request $request,
        float $executionTime,
        int $memoryUsage,
        int $statusCode,
        array $additionalData = []
    ): void {
        try {
            $logData = [
                'timestamp' => now()->toISOString(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => $request->route()?->getName(),
                'controller' => $this->getControllerAction($request),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'status_code' => $statusCode,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $this->getRequestId(),
                'query_count' => $this->getQueryCount(),
                'additional_data' => $additionalData
            ];

            // Determinar nível do log baseado na performance
            $level = $this->determinePerformanceLogLevel($executionTime, $memoryUsage, $statusCode);

            // Log no canal de performance
            Log::channel('performance')->{$level}('API Request', $logData);

            // Armazenar métricas em cache para dashboard
            $this->cachePerformanceMetrics($logData);

            // Alertas para performance crítica
            if ($level === 'warning' || $level === 'error') {
                $this->sendPerformanceAlert($logData);
            }
        } catch (Exception $e) {
            Log::error('Failed to log API performance', [
                'url' => $request->fullUrl(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Registra erro de segurança
     *
     * @param string $threat
     * @param string $description
     * @param array $context
     * @param string $severity
     * @return void
     */
    public function logSecurityThreat(
        string $threat,
        string $description,
        array $context = [],
        string $severity = 'warning'
    ): void {
        try {
            $logData = [
                'timestamp' => now()->toISOString(),
                'threat_type' => $threat,
                'description' => $description,
                'severity' => $severity,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
                'request_id' => $this->getRequestId(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'context' => $context,
                'headers' => $this->getSafeHeaders(),
                'geo_location' => $this->getGeoLocation(request()->ip())
            ];

            // Log no canal de segurança
            Log::channel('security')->{$severity}($threat, $logData);

            // Log crítico também vai para o canal principal
            if (in_array($severity, ['emergency', 'alert', 'critical', 'error'])) {
                Log::{$severity}("Security Threat - {$threat}", $logData);
            }

            // Armazenar em cache para análise rápida
            $this->cacheSecurityEvent($logData);

            // Enviar alerta imediato para ameaças críticas
            if (in_array($severity, ['emergency', 'alert', 'critical'])) {
                $this->sendSecurityAlert($logData);
            }
        } catch (Exception $e) {
            Log::error('Failed to log security threat', [
                'threat' => $threat,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Registra operação de arquivo
     *
     * @param string $operation
     * @param string $filename
     * @param array $metadata
     * @param bool $success
     * @return void
     */
    public function logFileOperation(
        string $operation,
        string $filename,
        array $metadata = [],
        bool $success = true
    ): void {
        try {
            $logData = [
                'timestamp' => now()->toISOString(),
                'operation' => $operation,
                'filename' => $filename,
                'success' => $success,
                'metadata' => $metadata,
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'request_id' => $this->getRequestId()
            ];

            $level = $success ? 'info' : 'error';
            
            Log::channel('files')->{$level}("File {$operation}", $logData);

            // Log de atividade do usuário
            $this->logUserActivity(
                "file_{$operation}",
                'file',
                ['filename' => $filename, 'success' => $success],
                null,
                $level
            );
        } catch (Exception $e) {
            Log::error('Failed to log file operation', [
                'operation' => $operation,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtém atividades recentes do usuário
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserRecentActivities(int $userId, int $limit = 50): array
    {
        $cacheKey = "user_activities_{$userId}";
        $activities = Cache::get($cacheKey, []);
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Obtém métricas de performance
     *
     * @param string $period
     * @return array
     */
    public function getPerformanceMetrics(string $period = '1h'): array
    {
        $cacheKey = "performance_metrics_{$period}";
        return Cache::get($cacheKey, [
            'avg_response_time' => 0,
            'max_response_time' => 0,
            'min_response_time' => 0,
            'total_requests' => 0,
            'error_rate' => 0,
            'avg_memory_usage' => 0
        ]);
    }

    /**
     * Obtém eventos de segurança recentes
     *
     * @param int $limit
     * @return array
     */
    public function getRecentSecurityEvents(int $limit = 100): array
    {
        $cacheKey = 'recent_security_events';
        $events = Cache::get($cacheKey, []);
        
        return array_slice($events, 0, $limit);
    }

    /**
     * Armazena atividade do usuário em cache
     *
     * @param int $userId
     * @param array $activityData
     * @return void
     */
    private function cacheUserActivity(int $userId, array $activityData): void
    {
        $cacheKey = "user_activities_{$userId}";
        $activities = Cache::get($cacheKey, []);
        
        // Adicionar nova atividade no início
        array_unshift($activities, $activityData);
        
        // Manter apenas as últimas 100 atividades
        $activities = array_slice($activities, 0, 100);
        
        // Cache por 24 horas
        Cache::put($cacheKey, $activities, 1440);
    }

    /**
     * Armazena métricas de performance em cache
     *
     * @param array $performanceData
     * @return void
     */
    private function cachePerformanceMetrics(array $performanceData): void
    {
        $periods = ['1h', '6h', '24h'];
        
        foreach ($periods as $period) {
            $cacheKey = "performance_metrics_{$period}";
            $metrics = Cache::get($cacheKey, [
                'requests' => [],
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'min_response_time' => PHP_INT_MAX,
                'total_requests' => 0,
                'error_count' => 0,
                'total_memory' => 0
            ]);
            
            // Adicionar nova métrica
            $metrics['requests'][] = $performanceData;
            $metrics['total_requests']++;
            $metrics['total_memory'] += $performanceData['memory_usage_mb'];
            
            // Atualizar estatísticas
            $responseTime = $performanceData['execution_time_ms'];
            $metrics['max_response_time'] = max($metrics['max_response_time'], $responseTime);
            $metrics['min_response_time'] = min($metrics['min_response_time'], $responseTime);
            
            if ($performanceData['status_code'] >= 400) {
                $metrics['error_count']++;
            }
            
            // Calcular médias
            $totalTime = array_sum(array_column($metrics['requests'], 'execution_time_ms'));
            $metrics['avg_response_time'] = $totalTime / $metrics['total_requests'];
            $metrics['avg_memory_usage'] = $metrics['total_memory'] / $metrics['total_requests'];
            $metrics['error_rate'] = ($metrics['error_count'] / $metrics['total_requests']) * 100;
            
            // Limpar dados antigos baseado no período
            $cutoff = $this->getCutoffTime($period);
            $metrics['requests'] = array_filter($metrics['requests'], function ($request) use ($cutoff) {
                return Carbon::parse($request['timestamp'])->isAfter($cutoff);
            });
            
            Cache::put($cacheKey, $metrics, $this->getCacheTtl($period));
        }
    }

    /**
     * Armazena evento de segurança em cache
     *
     * @param array $securityData
     * @return void
     */
    private function cacheSecurityEvent(array $securityData): void
    {
        $cacheKey = 'recent_security_events';
        $events = Cache::get($cacheKey, []);
        
        // Adicionar novo evento no início
        array_unshift($events, $securityData);
        
        // Manter apenas os últimos 500 eventos
        $events = array_slice($events, 0, 500);
        
        // Cache por 48 horas
        Cache::put($cacheKey, $events, 2880);
    }

    /**
     * Determina o nível do log baseado na performance
     *
     * @param float $executionTime
     * @param int $memoryUsage
     * @param int $statusCode
     * @return string
     */
    private function determinePerformanceLogLevel(
        float $executionTime,
        int $memoryUsage,
        int $statusCode
    ): string {
        // Erro HTTP
        if ($statusCode >= 500) {
            return 'error';
        }
        
        if ($statusCode >= 400) {
            return 'warning';
        }
        
        // Performance crítica
        if ($executionTime > 5.0 || $memoryUsage > 256 * 1024 * 1024) {
            return 'error';
        }
        
        // Performance ruim
        if ($executionTime > 2.0 || $memoryUsage > 128 * 1024 * 1024) {
            return 'warning';
        }
        
        // Performance lenta
        if ($executionTime > 1.0 || $memoryUsage > 64 * 1024 * 1024) {
            return 'notice';
        }
        
        return 'info';
    }

    /**
     * Obtém ID único da requisição
     *
     * @return string
     */
    private function getRequestId(): string
    {
        return request()->header('X-Request-ID') ?: uniqid('req_', true);
    }

    /**
     * Obtém ação do controller
     *
     * @param Request $request
     * @return string|null
     */
    private function getControllerAction(Request $request): ?string
    {
        $route = $request->route();
        return $route ? $route->getActionName() : null;
    }

    /**
     * Obtém contagem de queries do banco
     *
     * @return int
     */
    private function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    /**
     * Obtém headers seguros (sem informações sensíveis)
     *
     * @return array
     */
    private function getSafeHeaders(): array
    {
        $headers = request()->headers->all();
        
        // Remover headers sensíveis
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        
        foreach ($sensitiveHeaders as $header) {
            unset($headers[$header]);
        }
        
        return $headers;
    }

    /**
     * Obtém localização geográfica do IP (mock)
     *
     * @param string $ip
     * @return array
     */
    private function getGeoLocation(string $ip): array
    {
        // Em produção, usar serviço de geolocalização real
        return [
            'ip' => $ip,
            'country' => 'Unknown',
            'city' => 'Unknown',
            'region' => 'Unknown'
        ];
    }

    /**
     * Obtém tempo de corte baseado no período
     *
     * @param string $period
     * @return Carbon
     */
    private function getCutoffTime(string $period): Carbon
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            default => now()->subHour()
        };
    }

    /**
     * Obtém TTL do cache baseado no período
     *
     * @param string $period
     * @return int
     */
    private function getCacheTtl(string $period): int
    {
        return match ($period) {
            '1h' => 60,    // 1 hora
            '6h' => 360,   // 6 horas
            '24h' => 1440, // 24 horas
            default => 60
        };
    }

    /**
     * Envia alerta de performance
     *
     * @param array $performanceData
     * @return void
     */
    private function sendPerformanceAlert(array $performanceData): void
    {
        // Implementar notificação (email, Slack, etc.)
        Log::alert('Performance Alert', $performanceData);
    }

    /**
     * Envia alerta de segurança
     *
     * @param array $securityData
     * @return void
     */
    private function sendSecurityAlert(array $securityData): void
    {
        // Implementar notificação imediata (email, SMS, Slack, etc.)
        Log::emergency('Security Alert', $securityData);
    }
}