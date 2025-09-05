<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class WhatsAppService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.base_url', 'https://api.whatsapp.com');
        $this->apiKey = config('whatsapp.api_key');
        $this->timeout = config('whatsapp.timeout', 30);
    }

    /**
     * Conecta uma instância do WhatsApp
     *
     * @param Company $company
     * @param string $instanceId
     * @param string $token
     * @return array
     */
    public function connectInstance(Company $company, string $instanceId, string $token): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/instances/connect', [
                    'instance_id' => $instanceId,
                    'token' => $token,
                    'company_id' => $company->id,
                    'webhook_url' => route('webhook.whatsapp', ['company' => $company->id])
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Atualizar dados da empresa
                $company->update([
                    'whatsapp_instance_id' => $instanceId,
                    'whatsapp_token' => encrypt($token),
                    'whatsapp_connected' => true,
                    'whatsapp_connected_at' => now()
                ]);

                // Cache da conexão
                Cache::put("whatsapp_instance_{$company->id}", [
                    'instance_id' => $instanceId,
                    'connected' => true,
                    'connected_at' => now()
                ], 3600);

                Log::info('WhatsApp instance connected', [
                    'company_id' => $company->id,
                    'instance_id' => $instanceId
                ]);

                return [
                    'success' => true,
                    'message' => 'Instância conectada com sucesso',
                    'data' => $data
                ];
            }

            throw new Exception('Falha na conexão: ' . $response->body());
        } catch (Exception $e) {
            Log::error('WhatsApp connection failed', [
                'company_id' => $company->id,
                'instance_id' => $instanceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao conectar instância: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Desconecta uma instância do WhatsApp
     *
     * @param Company $company
     * @return array
     */
    public function disconnectInstance(Company $company): array
    {
        try {
            if (!$company->whatsapp_instance_id) {
                return [
                    'success' => false,
                    'message' => 'Nenhuma instância conectada'
                ];
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/instances/disconnect', [
                    'instance_id' => $company->whatsapp_instance_id
                ]);

            // Atualizar dados da empresa independente da resposta da API
            $company->update([
                'whatsapp_instance_id' => null,
                'whatsapp_token' => null,
                'whatsapp_connected' => false,
                'whatsapp_connected_at' => null
            ]);

            // Limpar cache
            Cache::forget("whatsapp_instance_{$company->id}");

            Log::info('WhatsApp instance disconnected', [
                'company_id' => $company->id
            ]);

            return [
                'success' => true,
                'message' => 'Instância desconectada com sucesso'
            ];
        } catch (Exception $e) {
            Log::error('WhatsApp disconnection failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao desconectar instância: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Envia uma mensagem de texto
     *
     * @param Company $company
     * @param string $phone
     * @param string $message
     * @return array
     */
    public function sendTextMessage(Company $company, string $phone, string $message): array
    {
        if (!$this->isInstanceConnected($company)) {
            return [
                'success' => false,
                'message' => 'Instância do WhatsApp não está conectada'
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/messages/text', [
                    'instance_id' => $company->whatsapp_instance_id,
                    'phone' => $this->formatPhoneNumber($phone),
                    'message' => $message
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('WhatsApp message sent', [
                    'company_id' => $company->id,
                    'phone' => $phone,
                    'message_id' => $data['message_id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message' => 'Mensagem enviada com sucesso',
                    'data' => $data
                ];
            }

            throw new Exception('Falha no envio: ' . $response->body());
        } catch (Exception $e) {
            Log::error('WhatsApp message failed', [
                'company_id' => $company->id,
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Envia uma mensagem com imagem
     *
     * @param Company $company
     * @param string $phone
     * @param string $imageUrl
     * @param string|null $caption
     * @return array
     */
    public function sendImageMessage(
        Company $company,
        string $phone,
        string $imageUrl,
        ?string $caption = null
    ): array {
        if (!$this->isInstanceConnected($company)) {
            return [
                'success' => false,
                'message' => 'Instância do WhatsApp não está conectada'
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/messages/image', [
                    'instance_id' => $company->whatsapp_instance_id,
                    'phone' => $this->formatPhoneNumber($phone),
                    'image_url' => $imageUrl,
                    'caption' => $caption
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('WhatsApp image sent', [
                    'company_id' => $company->id,
                    'phone' => $phone,
                    'image_url' => $imageUrl,
                    'message_id' => $data['message_id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message' => 'Imagem enviada com sucesso',
                    'data' => $data
                ];
            }

            throw new Exception('Falha no envio: ' . $response->body());
        } catch (Exception $e) {
            Log::error('WhatsApp image failed', [
                'company_id' => $company->id,
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao enviar imagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processa mensagem recebida via webhook
     *
     * @param array $webhookData
     * @return array
     */
    public function processIncomingMessage(array $webhookData): array
    {
        try {
            $instanceId = $webhookData['instance_id'] ?? null;
            $messageData = $webhookData['message'] ?? [];
            
            if (!$instanceId || empty($messageData)) {
                throw new Exception('Dados do webhook inválidos');
            }

            // Encontrar empresa pela instância
            $company = Company::where('whatsapp_instance_id', $instanceId)->first();
            
            if (!$company) {
                throw new Exception('Empresa não encontrada para a instância: ' . $instanceId);
            }

            $phone = $messageData['from'] ?? null;
            $message = $messageData['body'] ?? '';
            $messageType = $messageData['type'] ?? 'text';
            $messageId = $messageData['id'] ?? null;

            // Processar diferentes tipos de mensagem
            $response = match ($messageType) {
                'text' => $this->processTextMessage($company, $phone, $message),
                'image' => $this->processImageMessage($company, $phone, $messageData),
                'document' => $this->processDocumentMessage($company, $phone, $messageData),
                default => $this->processUnsupportedMessage($company, $phone, $messageType)
            };

            Log::info('WhatsApp message processed', [
                'company_id' => $company->id,
                'phone' => $phone,
                'message_type' => $messageType,
                'message_id' => $messageId
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('WhatsApp message processing failed', [
                'webhook_data' => $webhookData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar mensagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processa mensagem de texto recebida
     *
     * @param Company $company
     * @param string $phone
     * @param string $message
     * @return array
     */
    private function processTextMessage(Company $company, string $phone, string $message): array
    {
        // Verificar se é uma mensagem de comando
        $command = strtolower(trim($message));
        
        return match ($command) {
            'oi', 'olá', 'ola', 'hello', 'hi' => $this->sendGreetingMessage($company, $phone),
            'cardapio', 'cardápio', 'menu' => $this->sendMenuMessage($company, $phone),
            'pedido', 'fazer pedido' => $this->sendOrderInstructions($company, $phone),
            'contato', 'telefone' => $this->sendContactInfo($company, $phone),
            'horario', 'horário' => $this->sendBusinessHours($company, $phone),
            default => $this->sendDefaultResponse($company, $phone)
        };
    }

    /**
     * Envia mensagem de saudação
     *
     * @param Company $company
     * @param string $phone
     * @return array
     */
    private function sendGreetingMessage(Company $company, string $phone): array
    {
        $message = "Olá! 👋 Bem-vindo(a) ao *{$company->name}*!\n\n";
        $message .= "Como posso ajudá-lo(a) hoje?\n\n";
        $message .= "📋 Digite *cardápio* para ver nosso menu\n";
        $message .= "🛒 Digite *pedido* para fazer um pedido\n";
        $message .= "📞 Digite *contato* para falar conosco\n";
        $message .= "🕒 Digite *horário* para ver nosso funcionamento";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Envia informações do cardápio
     *
     * @param Company $company
     * @param string $phone
     * @return array
     */
    private function sendMenuMessage(Company $company, string $phone): array
    {
        $message = "📋 *CARDÁPIO - {$company->name}*\n\n";
        $message .= "Para ver nosso cardápio completo, acesse:\n";
        $message .= "🌐 {$company->website}\n\n";
        $message .= "Ou baixe nosso app para fazer pedidos com facilidade!\n\n";
        $message .= "📱 *App Store / Google Play*";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Envia instruções para fazer pedido
     *
     * @param Company $company
     * @param string $phone
     * @return array
     */
    private function sendOrderInstructions(Company $company, string $phone): array
    {
        $message = "🛒 *COMO FAZER SEU PEDIDO*\n\n";
        $message .= "1️⃣ Acesse nosso site: {$company->website}\n";
        $message .= "2️⃣ Escolha seus itens favoritos\n";
        $message .= "3️⃣ Adicione ao carrinho\n";
        $message .= "4️⃣ Finalize seu pedido\n\n";
        $message .= "💳 *Formas de pagamento:*\n";
        $message .= "• Cartão de crédito/débito\n";
        $message .= "• PIX\n";
        $message .= "• Dinheiro na entrega\n\n";
        $message .= "🚚 *Entrega rápida em toda a região!*";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Envia informações de contato
     *
     * @param Company $company
     * @param string $phone
     * @return array
     */
    private function sendContactInfo(Company $company, string $phone): array
    {
        $message = "📞 *NOSSOS CONTATOS*\n\n";
        $message .= "🏢 *{$company->name}*\n";
        $message .= "📱 WhatsApp: {$company->phone}\n";
        $message .= "📧 Email: {$company->email}\n";
        $message .= "📍 Endereço: {$company->address}\n";
        $message .= "🌐 Site: {$company->website}";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Envia horário de funcionamento
     *
     * @param Company $company
     * @param string $phone
     * @return array
     */
    private function sendBusinessHours(Company $company, string $phone): array
    {
        $message = "🕒 *HORÁRIO DE FUNCIONAMENTO*\n\n";
        $message .= "📅 Segunda a Sexta: 11h às 23h\n";
        $message .= "📅 Sábado: 11h às 00h\n";
        $message .= "📅 Domingo: 18h às 23h\n\n";
        $message .= "⚠️ *Atenção:* Horários podem variar em feriados";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Envia resposta padrão
     *
     * @param Company $company
     * @param string $phone
     * @return array
     */
    private function sendDefaultResponse(Company $company, string $phone): array
    {
        $message = "Obrigado pela sua mensagem! 😊\n\n";
        $message .= "Para melhor atendê-lo(a), digite uma das opções:\n\n";
        $message .= "📋 *cardápio* - Ver nosso menu\n";
        $message .= "🛒 *pedido* - Fazer um pedido\n";
        $message .= "📞 *contato* - Nossos contatos\n";
        $message .= "🕒 *horário* - Horário de funcionamento\n\n";
        $message .= "Ou acesse diretamente: {$company->website}";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Processa mensagem de imagem recebida
     *
     * @param Company $company
     * @param string $phone
     * @param array $messageData
     * @return array
     */
    private function processImageMessage(Company $company, string $phone, array $messageData): array
    {
        $message = "📸 Obrigado pela imagem!\n\n";
        $message .= "Para fazer pedidos, acesse nosso site:\n";
        $message .= "🌐 {$company->website}";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Processa mensagem de documento recebida
     *
     * @param Company $company
     * @param string $phone
     * @param array $messageData
     * @return array
     */
    private function processDocumentMessage(Company $company, string $phone, array $messageData): array
    {
        $message = "📄 Obrigado pelo documento!\n\n";
        $message .= "Para mais informações, entre em contato:\n";
        $message .= "📱 {$company->phone}";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Processa tipo de mensagem não suportado
     *
     * @param Company $company
     * @param string $phone
     * @param string $messageType
     * @return array
     */
    private function processUnsupportedMessage(Company $company, string $phone, string $messageType): array
    {
        $message = "Desculpe, não conseguimos processar este tipo de mensagem.\n\n";
        $message .= "Para melhor atendimento, envie uma mensagem de texto ou acesse:\n";
        $message .= "🌐 {$company->website}";

        return $this->sendTextMessage($company, $phone, $message);
    }

    /**
     * Verifica se a instância está conectada
     *
     * @param Company $company
     * @return bool
     */
    private function isInstanceConnected(Company $company): bool
    {
        if (!$company->whatsapp_connected || !$company->whatsapp_instance_id) {
            return false;
        }

        // Verificar cache primeiro
        $cached = Cache::get("whatsapp_instance_{$company->id}");
        if ($cached && $cached['connected']) {
            return true;
        }

        // Verificar status na API
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey
                ])
                ->get($this->baseUrl . '/instances/status/' . $company->whatsapp_instance_id);

            if ($response->successful()) {
                $data = $response->json();
                $connected = $data['connected'] ?? false;

                // Atualizar cache
                Cache::put("whatsapp_instance_{$company->id}", [
                    'instance_id' => $company->whatsapp_instance_id,
                    'connected' => $connected,
                    'checked_at' => now()
                ], 300); // 5 minutos

                return $connected;
            }
        } catch (Exception $e) {
            Log::warning('Failed to check WhatsApp instance status', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    /**
     * Formata número de telefone
     *
     * @param string $phone
     * @return string
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove todos os caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Adiciona código do país se não tiver
        if (strlen($phone) === 11 && substr($phone, 0, 1) !== '55') {
            $phone = '55' . $phone;
        }
        
        return $phone;
    }

    /**
     * Envia notificação de novo pedido
     *
     * @param Order $order
     * @return array
     */
    public function sendOrderNotification(Order $order): array
    {
        $company = $order->company;
        
        if (!$company || !$company->whatsapp_connected) {
            return [
                'success' => false,
                'message' => 'WhatsApp não configurado para esta empresa'
            ];
        }

        $message = "🆕 *NOVO PEDIDO RECEBIDO!*\n\n";
        $message .= "📋 *Pedido:* #{$order->order_number}\n";
        $message .= "👤 *Cliente:* {$order->user->name}\n";
        $message .= "📱 *Telefone:* {$order->user->phone}\n";
        $message .= "💰 *Total:* R$ " . number_format($order->total, 2, ',', '.') . "\n";
        $message .= "🚚 *Entrega:* {$order->delivery_address}\n";
        $message .= "⏰ *Horário:* " . $order->created_at->format('d/m/Y H:i');

        // Enviar para o número da empresa
        return $this->sendTextMessage($company, $company->phone, $message);
    }

    /**
     * Obtém estatísticas da instância
     *
     * @param Company $company
     * @return array
     */
    public function getInstanceStats(Company $company): array
    {
        if (!$this->isInstanceConnected($company)) {
            return [
                'success' => false,
                'message' => 'Instância não conectada'
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey
                ])
                ->get($this->baseUrl . '/instances/stats/' . $company->whatsapp_instance_id);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            throw new Exception('Falha ao obter estatísticas: ' . $response->body());
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao obter estatísticas: ' . $e->getMessage()
            ];
        }
    }
}