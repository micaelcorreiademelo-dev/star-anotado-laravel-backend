<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppInstance;

class ValidateWhatsAppWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se é uma requisição de webhook
        if (!$request->is('api/webhooks/whatsapp/*')) {
            return $next($request);
        }

        // Extrair instance_id da URL
        $instanceId = $request->route('instance');
        
        if (!$instanceId) {
            Log::warning('WhatsApp webhook: Instance ID não fornecido', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'error' => 'Instance ID é obrigatório'
            ], 400);
        }

        // Verificar se a instância existe
        $instance = WhatsAppInstance::where('instance_id', $instanceId)->first();
        
        if (!$instance) {
            Log::warning('WhatsApp webhook: Instância não encontrada', [
                'instance_id' => $instanceId,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'error' => 'Instância não encontrada'
            ], 404);
        }

        // Verificar se a instância está ativa
        if (!$instance->is_active) {
            Log::warning('WhatsApp webhook: Instância inativa', [
                'instance_id' => $instanceId,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'error' => 'Instância inativa'
            ], 403);
        }

        // Validar token se configurado
        $webhookToken = $request->header('X-Webhook-Token') ?? $request->get('token');
        $expectedToken = $instance->api_settings['webhook_token'] ?? null;
        
        if ($expectedToken && $webhookToken !== $expectedToken) {
            Log::warning('WhatsApp webhook: Token inválido', [
                'instance_id' => $instanceId,
                'provided_token' => $webhookToken,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'error' => 'Token de webhook inválido'
            ], 401);
        }

        // Validar User-Agent se configurado
        $userAgent = $request->header('User-Agent');
        $allowedUserAgents = $instance->api_settings['allowed_user_agents'] ?? [];
        
        if (!empty($allowedUserAgents) && !in_array($userAgent, $allowedUserAgents)) {
            Log::warning('WhatsApp webhook: User-Agent não permitido', [
                'instance_id' => $instanceId,
                'user_agent' => $userAgent,
                'allowed' => $allowedUserAgents,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'error' => 'User-Agent não permitido'
            ], 403);
        }

        // Validar IP se configurado
        $clientIp = $request->ip();
        $allowedIps = $instance->api_settings['allowed_ips'] ?? [];
        
        if (!empty($allowedIps) && !in_array($clientIp, $allowedIps)) {
            Log::warning('WhatsApp webhook: IP não permitido', [
                'instance_id' => $instanceId,
                'client_ip' => $clientIp,
                'allowed_ips' => $allowedIps
            ]);
            
            return response()->json([
                'error' => 'IP não permitido'
            ], 403);
        }

        // Rate limiting básico
        $rateLimitKey = 'whatsapp_webhook:' . $instanceId . ':' . $clientIp;
        $maxRequests = $instance->api_settings['webhook_rate_limit'] ?? 100; // por minuto
        
        if (cache()->has($rateLimitKey)) {
            $currentCount = cache()->get($rateLimitKey);
            
            if ($currentCount >= $maxRequests) {
                Log::warning('WhatsApp webhook: Rate limit excedido', [
                    'instance_id' => $instanceId,
                    'client_ip' => $clientIp,
                    'current_count' => $currentCount,
                    'max_requests' => $maxRequests
                ]);
                
                return response()->json([
                    'error' => 'Rate limit excedido'
                ], 429);
            }
            
            cache()->put($rateLimitKey, $currentCount + 1, 60); // 1 minuto
        } else {
            cache()->put($rateLimitKey, 1, 60); // 1 minuto
        }

        // Adicionar instância ao request para uso posterior
        $request->merge(['whatsapp_instance' => $instance]);

        // Log da requisição válida
        Log::info('WhatsApp webhook: Requisição válida', [
            'instance_id' => $instanceId,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'content_type' => $request->header('Content-Type')
        ]);

        return $next($request);
    }
}