<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações gerais para o módulo WhatsApp
    |
    */

    'default_api_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'evolution'),

    /*
    |--------------------------------------------------------------------------
    | API Providers
    |--------------------------------------------------------------------------
    |
    | Configurações para diferentes provedores de API WhatsApp
    |
    */

    'providers' => [
        'evolution' => [
            'base_url' => env('WHATSAPP_EVOLUTION_BASE_URL', 'http://localhost:8080'),
            'global_api_key' => env('WHATSAPP_EVOLUTION_API_KEY'),
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
        ],
        
        'baileys' => [
            'base_url' => env('WHATSAPP_BAILEYS_BASE_URL', 'http://localhost:3000'),
            'api_key' => env('WHATSAPP_BAILEYS_API_KEY'),
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000,
        ],
        
        'wppconnect' => [
            'base_url' => env('WHATSAPP_WPPCONNECT_BASE_URL', 'http://localhost:21465'),
            'secret_key' => env('WHATSAPP_WPPCONNECT_SECRET_KEY'),
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações das filas para processamento assíncrono
    |
    */

    'queues' => [
        'messages' => env('WHATSAPP_QUEUE_MESSAGES', 'whatsapp-messages'),
        'greetings' => env('WHATSAPP_QUEUE_GREETINGS', 'whatsapp-greetings'),
        'chatbot' => env('WHATSAPP_QUEUE_CHATBOT', 'whatsapp-chatbot'),
        'status' => env('WHATSAPP_QUEUE_STATUS', 'whatsapp-status'),
        'connection' => env('WHATSAPP_QUEUE_CONNECTION', 'whatsapp-connection'),
        'default' => env('WHATSAPP_QUEUE_DEFAULT', 'whatsapp-default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para webhooks
    |
    */

    'webhook' => [
        'base_url' => env('APP_URL', 'http://localhost') . '/api/webhooks/whatsapp',
        'timeout' => 30,
        'verify_ssl' => env('WHATSAPP_WEBHOOK_VERIFY_SSL', true),
        'max_retries' => 3,
        'retry_delay' => 5, // seconds
        
        // Rate limiting
        'rate_limit' => [
            'enabled' => env('WHATSAPP_WEBHOOK_RATE_LIMIT', true),
            'max_requests' => env('WHATSAPP_WEBHOOK_MAX_REQUESTS', 100), // per minute
            'window' => 60, // seconds
        ],
        
        // Security
        'security' => [
            'validate_token' => env('WHATSAPP_WEBHOOK_VALIDATE_TOKEN', true),
            'validate_ip' => env('WHATSAPP_WEBHOOK_VALIDATE_IP', false),
            'validate_user_agent' => env('WHATSAPP_WEBHOOK_VALIDATE_USER_AGENT', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para mensagens
    |
    */

    'messages' => [
        'max_length' => env('WHATSAPP_MESSAGE_MAX_LENGTH', 4096),
        'allowed_types' => [
            'text', 'image', 'video', 'audio', 'document', 
            'location', 'contact', 'sticker'
        ],
        
        // Media settings
        'media' => [
            'max_file_size' => env('WHATSAPP_MEDIA_MAX_SIZE', 16 * 1024 * 1024), // 16MB
            'allowed_extensions' => [
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'video' => ['mp4', 'avi', 'mov', 'wmv', '3gp'],
                'audio' => ['mp3', 'wav', 'ogg', 'aac', 'm4a'],
                'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
            ],
            'storage_disk' => env('WHATSAPP_MEDIA_DISK', 'public'),
            'storage_path' => 'whatsapp/media',
        ],
        
        // Auto-responses
        'auto_responses' => [
            'greeting_delay' => env('WHATSAPP_GREETING_DELAY', 2), // seconds
            'chatbot_delay' => env('WHATSAPP_CHATBOT_DELAY', 1), // seconds
            'max_responses_per_conversation' => env('WHATSAPP_MAX_RESPONSES', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chatbot Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para o chatbot
    |
    */

    'chatbot' => [
        'enabled' => env('WHATSAPP_CHATBOT_ENABLED', true),
        'max_keywords' => env('WHATSAPP_CHATBOT_MAX_KEYWORDS', 20),
        'case_sensitive' => env('WHATSAPP_CHATBOT_CASE_SENSITIVE', false),
        'response_delay' => env('WHATSAPP_CHATBOT_RESPONSE_DELAY', 1), // seconds
        'max_responses_per_message' => env('WHATSAPP_CHATBOT_MAX_RESPONSES', 1),
        
        // AI Integration (future)
        'ai' => [
            'enabled' => env('WHATSAPP_AI_ENABLED', false),
            'provider' => env('WHATSAPP_AI_PROVIDER', 'openai'),
            'model' => env('WHATSAPP_AI_MODEL', 'gpt-3.5-turbo'),
            'max_tokens' => env('WHATSAPP_AI_MAX_TOKENS', 150),
            'temperature' => env('WHATSAPP_AI_TEMPERATURE', 0.7),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Greeting Messages Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para mensagens de saudação
    |
    */

    'greetings' => [
        'enabled' => env('WHATSAPP_GREETINGS_ENABLED', true),
        'default_delay' => env('WHATSAPP_GREETING_DEFAULT_DELAY', 2), // seconds
        'max_per_contact_per_day' => env('WHATSAPP_GREETING_MAX_PER_DAY', 1),
        'business_hours_only' => env('WHATSAPP_GREETING_BUSINESS_HOURS_ONLY', false),
        
        'default_business_hours' => [
            'start' => env('WHATSAPP_BUSINESS_START', '09:00'),
            'end' => env('WHATSAPP_BUSINESS_END', '18:00'),
            'days' => [1, 2, 3, 4, 5], // Monday to Friday
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações de cache
    |
    */

    'cache' => [
        'enabled' => env('WHATSAPP_CACHE_ENABLED', true),
        'ttl' => [
            'instance_status' => env('WHATSAPP_CACHE_INSTANCE_TTL', 300), // 5 minutes
            'conversation' => env('WHATSAPP_CACHE_CONVERSATION_TTL', 1800), // 30 minutes
            'chatbot_responses' => env('WHATSAPP_CACHE_CHATBOT_TTL', 3600), // 1 hour
            'greeting_messages' => env('WHATSAPP_CACHE_GREETING_TTL', 3600), // 1 hour
            'stats' => env('WHATSAPP_CACHE_STATS_TTL', 900), // 15 minutes
        ],
        'prefix' => 'whatsapp:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações de logging
    |
    */

    'logging' => [
        'enabled' => env('WHATSAPP_LOGGING_ENABLED', true),
        'level' => env('WHATSAPP_LOG_LEVEL', 'info'),
        'channels' => [
            'messages' => env('WHATSAPP_LOG_MESSAGES', true),
            'webhooks' => env('WHATSAPP_LOG_WEBHOOKS', true),
            'api_calls' => env('WHATSAPP_LOG_API_CALLS', true),
            'errors' => env('WHATSAPP_LOG_ERRORS', true),
        ],
        'retention_days' => env('WHATSAPP_LOG_RETENTION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações de performance
    |
    */

    'performance' => [
        'batch_size' => env('WHATSAPP_BATCH_SIZE', 100),
        'max_concurrent_requests' => env('WHATSAPP_MAX_CONCURRENT', 10),
        'request_timeout' => env('WHATSAPP_REQUEST_TIMEOUT', 30),
        'connection_pool_size' => env('WHATSAPP_CONNECTION_POOL', 5),
        
        // Database optimization
        'database' => [
            'chunk_size' => env('WHATSAPP_DB_CHUNK_SIZE', 1000),
            'index_optimization' => env('WHATSAPP_DB_INDEX_OPTIMIZATION', true),
            'query_cache' => env('WHATSAPP_DB_QUERY_CACHE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações de segurança
    |
    */

    'security' => [
        'encrypt_messages' => env('WHATSAPP_ENCRYPT_MESSAGES', false),
        'encrypt_media' => env('WHATSAPP_ENCRYPT_MEDIA', false),
        'sanitize_input' => env('WHATSAPP_SANITIZE_INPUT', true),
        'validate_phone_numbers' => env('WHATSAPP_VALIDATE_PHONES', true),
        
        // Rate limiting
        'rate_limits' => [
            'messages_per_minute' => env('WHATSAPP_RATE_LIMIT_MESSAGES', 60),
            'api_calls_per_minute' => env('WHATSAPP_RATE_LIMIT_API', 100),
            'webhooks_per_minute' => env('WHATSAPP_RATE_LIMIT_WEBHOOKS', 200),
        ],
    ],

];