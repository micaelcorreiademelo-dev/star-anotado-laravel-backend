<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use App\Services\LoggingService;

class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Monitorar queries lentas do banco de dados
        $this->monitorSlowQueries();
        
        // Monitorar eventos de autenticação
        $this->monitorAuthEvents();
        
        // Registrar métricas do sistema periodicamente
        $this->registerSystemMetrics();
    }
    
    /**
     * Monitor slow database queries
     */
    private function monitorSlowQueries(): void
    {
        DB::listen(function (QueryExecuted $query) {
            // Log queries que demoram mais que 1 segundo
            $slowQueryThreshold = config('database.slow_query_threshold', 1000); // ms
            
            if ($query->time > $slowQueryThreshold) {
                LoggingService::logSlowQuery(
                    $query->sql,
                    $query->bindings,
                    $query->time
                );
            }
            
            // Log todas as queries em modo debug
            if (config('app.debug') && config('logging.log_all_queries', false)) {
                LoggingService::logUserActivity('database_query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName
                ]);
            }
        });
    }
    
    /**
     * Monitor authentication events
     */
    private function monitorAuthEvents(): void
    {
        // Login bem-sucedido
        Event::listen(Login::class, function (Login $event) {
            LoggingService::logLoginAttempt(
                $event->user->email,
                true,
                'successful_login'
            );
            
            LoggingService::logUserActivity('user_login', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'guard' => $event->guard
            ]);
        });
        
        // Login falhado
        Event::listen(Failed::class, function (Failed $event) {
            LoggingService::logLoginAttempt(
                $event->credentials['email'] ?? 'unknown',
                false,
                'invalid_credentials'
            );
            
            LoggingService::logSecurityEvent('failed_login', [
                'email' => $event->credentials['email'] ?? 'unknown',
                'guard' => $event->guard
            ]);
        });
        
        // Logout
        Event::listen(Logout::class, function (Logout $event) {
            LoggingService::logUserActivity('user_logout', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'guard' => $event->guard
            ]);
        });
    }
    
    /**
     * Register system metrics collection
     */
    private function registerSystemMetrics(): void
    {
        // Registrar métricas do sistema a cada 5 minutos em produção
        if (config('app.env') === 'production') {
            $this->app->booted(function () {
                // Usar um job ou scheduler para coletar métricas periodicamente
                if (config('monitoring.collect_system_metrics', true)) {
                    LoggingService::logSystemMetrics();
                }
            });
        }
    }
    
    /**
     * Register custom error handler
     */
    private function registerErrorHandler(): void
    {
        // Capturar erros não tratados
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            
            LoggingService::logSecurityEvent('php_error', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line
            ]);
            
            return false; // Permitir que o handler padrão também processe
        });
        
        // Capturar exceções não tratadas
        set_exception_handler(function (\Throwable $exception) {
            LoggingService::logError($exception, [
                'handler' => 'global_exception_handler'
            ]);
        });
    }
    
    /**
     * Monitor cache events
     */
    private function monitorCacheEvents(): void
    {
        // Monitorar eventos de cache se disponível
        if (class_exists('\Illuminate\Cache\Events\CacheHit')) {
            Event::listen('\Illuminate\Cache\Events\CacheHit', function ($event) {
                LoggingService::logCacheEvent($event->key, 'hit', $event->value);
            });
            
            Event::listen('\Illuminate\Cache\Events\CacheMissed', function ($event) {
                LoggingService::logCacheEvent($event->key, 'miss');
            });
            
            Event::listen('\Illuminate\Cache\Events\KeyWritten', function ($event) {
                LoggingService::logCacheEvent($event->key, 'set', $event->value);
            });
            
            Event::listen('\Illuminate\Cache\Events\KeyForgotten', function ($event) {
                LoggingService::logCacheEvent($event->key, 'delete');
            });
        }
    }
    
    /**
     * Monitor queue events
     */
    private function monitorQueueEvents(): void
    {
        // Monitorar jobs da fila se disponível
        if (class_exists('\Illuminate\Queue\Events\JobProcessing')) {
            Event::listen('\Illuminate\Queue\Events\JobProcessing', function ($event) {
                LoggingService::logQueueJob(
                    get_class($event->job->payload()['data']['command'] ?? 'Unknown'),
                    'started',
                    [
                        'queue' => $event->job->getQueue(),
                        'connection' => $event->connectionName
                    ]
                );
            });
            
            Event::listen('\Illuminate\Queue\Events\JobProcessed', function ($event) {
                LoggingService::logQueueJob(
                    get_class($event->job->payload()['data']['command'] ?? 'Unknown'),
                    'completed',
                    [
                        'queue' => $event->job->getQueue(),
                        'connection' => $event->connectionName
                    ]
                );
            });
            
            Event::listen('\Illuminate\Queue\Events\JobFailed', function ($event) {
                LoggingService::logQueueJob(
                    get_class($event->job->payload()['data']['command'] ?? 'Unknown'),
                    'failed',
                    [
                        'queue' => $event->job->getQueue(),
                        'connection' => $event->connectionName,
                        'exception' => $event->exception->getMessage()
                    ]
                );
            });
        }
    }
}