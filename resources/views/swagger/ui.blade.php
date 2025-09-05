<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StarAnotado API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
        }
        .swagger-ui .topbar {
            background-color: #2c3e50;
        }
        .swagger-ui .topbar .download-url-wrapper .select-label {
            color: #fff;
        }
        .swagger-ui .topbar .download-url-wrapper input[type=text] {
            border: 2px solid #34495e;
        }
        .swagger-ui .info .title {
            color: #2c3e50;
        }
        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 0;
        }
        .custom-header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .custom-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>🌟 StarAnotado API</h1>
        <p>Documentação Interativa da API - Sistema de Delivery</p>
    </div>
    
    <div id="swagger-ui"></div>
    
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '{{ url("/api/documentation/json") }}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                onComplete: function() {
                    console.log('Swagger UI carregado com sucesso!');
                },
                requestInterceptor: function(request) {
                    // Adiciona automaticamente o token de autenticação se disponível
                    const token = localStorage.getItem('api_token');
                    if (token) {
                        request.headers['Authorization'] = 'Bearer ' + token;
                    }
                    return request;
                },
                responseInterceptor: function(response) {
                    // Log das respostas para debug
                    console.log('API Response:', response);
                    return response;
                },
                docExpansion: 'list',
                apisSorter: 'alpha',
                operationsSorter: 'alpha',
                defaultModelsExpandDepth: 2,
                defaultModelExpandDepth: 2,
                showExtensions: true,
                showCommonExtensions: true,
                filter: true
            });
            
            // Função para salvar token de autenticação
            window.setApiToken = function(token) {
                localStorage.setItem('api_token', token);
                console.log('Token de API salvo!');
            };
            
            // Função para limpar token
            window.clearApiToken = function() {
                localStorage.removeItem('api_token');
                console.log('Token de API removido!');
            };
            
            // Adiciona botões de controle de token
            setTimeout(function() {
                const topbar = document.querySelector('.swagger-ui .topbar');
                if (topbar) {
                    const tokenControls = document.createElement('div');
                    tokenControls.innerHTML = `
                        <input type="text" id="token-input" placeholder="Cole seu token aqui" style="padding: 5px; border-radius: 3px; border: 1px solid #ccc; width: 200px;">
                        <button onclick="setApiToken(document.getElementById('token-input').value)" style="padding: 5px 10px; background: #4CAF50; color: white; border: none; border-radius: 3px; cursor: pointer;">Salvar Token</button>
                        <button onclick="clearApiToken()" style="padding: 5px 10px; background: #f44336; color: white; border: none; border-radius: 3px; cursor: pointer;">Limpar</button>
                    `;
                    topbar.style.position = 'relative';
                    topbar.appendChild(tokenControls);
                }
            }, 1000);
        };
    </script>
</body>
</html>