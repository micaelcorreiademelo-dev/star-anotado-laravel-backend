<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PerformanceMonitoring
{
    /**
     * Limite de tempo de execução em segundos para gerar alerta
     */
    private const SLOW_REQUEST_THRESHOLD = 2.0;

    /**
     * Limite de uso de memória em MB para gerar alerta
     */
    private const HIGH_MEMORY_THRESHOLD = 128;

    /**
     * Endpoints que devem ser monitorados mais rigorosamente
     */
    private const CRITICAL_ENDPOINTS = [
        'api/orders',
        'api/payments',
        'api/whatsapp/send',
        'api/auth/login'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $requestId = Str::uuid()->toString();
        
        // Adicionar ID da requisição aos headers para rastreamento
        $request->headers->set('X-Request-ID', $requestId);
        
        // Log do início da requisição
        $this->logRequestStart($request, $requestId);
        
        // Executar a requisição
        $response = $next($request);
        
        // Calcular métricas de performance
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $executionTime = $endTime - $startTime;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB
        
        // Adicionar headers de performance à resposta
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Execution-Time', number_format($executionTime, 4));
        $response->headers->set('X-Memory-Usage', number_format($memoryUsed, 2));
        $response->headers->set('X-Peak-Memory', number_format($peakMemory, 2));
        
        // Log das métricas de performance
        $this->logPerformanceMetrics($request, $response, [
            'request_id' => $requestId,
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'peak_memory' => $peakMemory,
            'status_code' => $response->getStatusCode()
        ]);
        
        // Verificar se é uma requisição lenta
        if ($executionTime > self::SLOW_REQUEST_THRESHOLD) {
            $this->logSlowRequest($request, $requestId, $executionTime);
        }
        
        // Verificar uso alto de memória
        if ($peakMemory > self::HIGH_MEMORY_THRESHOLD) {
            $this->logHighMemoryUsage($request, $requestId, $peakMemory);
        }
        
        // Monitoramento específico para endpoints críticos
        if ($this->isCriticalEndpoint($request)) {
            $this->monitorCriticalEndpoint($request, $requestId, $executionTime, $peakMemory);
        }
        
        // Atualizar estatísticas em cache
        $this->updatePerformanceStats($request, $executionTime, $memoryUsed);
        
        // Detectar possíveis ataques ou comportamentos suspeitos
        $this->detectSuspiciousActivity($request, $requestId);
        
        return $response;
    }
    
    /**
     * Log do início da requisição
     *
     * @param Request $request
     * @param string $requestId
     */
    private function logRequestStart(Request $request, string $requestId): void
    {
        Log::info('Request started', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Log das métricas de performance
     *
     * @param Request $request
     * @param Response $response
     * @param array $metrics
     */
    private function logPerformanceMetrics(Request $request, Response $response, array $metrics): void
    {
        $logLevel = $metrics['execution_time'] > self::SLOW_REQUEST_THRESHOLD ? 'warning' : 'info';
        
        Log::log($logLevel, 'Request completed', [
            'request_id' => $metrics['request_id'],
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $metrics['status_code'],
            'execution_time' => $metrics['execution_time'],
            'memory_used_mb' => $metrics['memory_used'],
            'peak_memory_mb' => $metrics['peak_memory'],
            'user_id' => auth()->id(),
            'ip' => $request->ip()
        ]);
    }
    
    /**
     * Log de requisição lenta
     *
     * @param Request $request
     * @param string $requestId
     * @param float $executionTime
     */
    private function logSlowRequest(Request $request, string $requestId, float $executionTime): void
    {
        Log::warning('Slow request detected', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'execution_time' => $executionTime,
            'threshold' => self::SLOW_REQUEST_THRESHOLD,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'query_params' => $request->query(),
            'route_name' => $request->route()?->getName()
        ]);
    }
    
    /**
     * Log de uso alto de memória
     *
     * @param Request $request
     * @param string $requestId
     * @param float $peakMemory
     */
    private function logHighMemoryUsage(Request $request, string $requestId, float $peakMemory): void
    {
        Log::warning('High memory usage detected', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'peak_memory_mb' => $peakMemory,
            'threshold_mb' => self::HIGH_MEMORY_THRESHOLD,
            'user_id' => auth()->id(),
            'ip' => $request->ip()
        ]);
    }
    
    /**
     * Verificar se é um endpoint crítico
     *
     * @param Request $request
     * @return bool
     */
    private function isCriticalEndpoint(Request $request): bool
    {
        $path = $request->path();
        
        foreach (self::CRITICAL_ENDPOINTS as $endpoint) {
            if (Str::startsWith($path, $endpoint)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Monitoramento específico para endpoints críticos
     *
     * @param Request $request
     * @param string $requestId
     * @param float $executionTime
     * @param float $peakMemory
     */
    private function monitorCriticalEndpoint(Request $request, string $requestId, float $executionTime, float $peakMemory): void
    {
        $criticalThreshold = self::SLOW_REQUEST_THRESHOLD * 0.5; // Threshold mais rigoroso
        
        if ($executionTime > $criticalThreshold) {
            Log::error('Critical endpoint performance issue', [
                'request_id' => $requestId,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'peak_memory_mb' => $peakMemory,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'severity' => 'high'
            ]);
        }
    }
    
    /**
     * Atualizar estatísticas de performance em cache
     *
     * @param Request $request
     * @param float $executionTime
     * @param float $memoryUsed
     */
    private function updatePerformanceStats(Request $request, float $executionTime, float $memoryUsed): void
    {
        $endpoint = $request->method() . ':' . $request->path();
        $cacheKey = 'performance_stats:' . md5($endpoint);
        
        $stats = Cache::get($cacheKey, [
            'total_requests' => 0,
            'total_time' => 0,
            'total_memory' => 0,
            'max_time' => 0,
            'max_memory' => 0
        ]);
        
        $stats['total_requests']++;
        $stats['total_time'] += $executionTime;
        $stats['total_memory'] += $memoryUsed;
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['max_memory'] = max($stats['max_memory'], $memoryUsed);
        $stats['avg_time'] = $stats['total_time'] / $stats['total_requests'];
        $stats['avg_memory'] = $stats['total_memory'] / $stats['total_requests'];
        
        Cache::put($cacheKey, $stats, 3600); // 1 hora
    }
    
    /**
     * Detectar atividade suspeita
     *
     * @param Request $request
     * @param string $requestId
     */
    private function detectSuspiciousActivity(Request $request, string $requestId): void
    {
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');
        
        // Rate limiting por IP
        $rateLimitKey = 'rate_limit:' . $ip;
        $requestCount = Cache::get($rateLimitKey, 0);
        
        if ($requestCount > 100) { // Mais de 100 requests por minuto
            Log::warning('Possible DDoS or abuse detected', [
                'request_id' => $requestId,
                'ip' => $ip,
                'request_count' => $requestCount,
                'user_agent' => $userAgent,
                'url' => $request->fullUrl()
            ]);
        }
        
        Cache::put($rateLimitKey, $requestCount + 1, 60); // 1 minuto
        
        // Detectar User-Agents suspeitos
        $suspiciousUserAgents = [
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'python-requests',
            'curl/7.'
        ];
        
        foreach ($suspiciousUserAgents as $suspicious) {
            if (stripos($userAgent, $suspicious) !== false) {
                Log::warning('Suspicious User-Agent detected', [
                    'request_id' => $requestId,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'url' => $request->fullUrl(),
                    'detected_pattern' => $suspicious
                ]);
                break;
            }
        }
        
        // Detectar tentativas de SQL injection ou XSS
        $maliciousPatterns = [
            'union select',
            'drop table',
            'insert into',
            '<script',
            'javascript:',
            'onload=',
            'onerror='
        ];
        
        $allInput = json_encode($request->all());
        
        foreach ($maliciousPatterns as $pattern) {
            if (stripos($allInput, $pattern) !== false) {
                Log::error('Malicious input detected', [
                    'request_id' => $requestId,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'url' => $request->fullUrl(),
                    'detected_pattern' => $pattern,
                    'input_data' => $request->all(),
                    'severity' => 'critical'
                ]);
                break;
            }
        }
    }
}