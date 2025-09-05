# Star Anotado - Laravel Backend

Sistema Laravel Backend completo com API REST, autenticação, monitoramento e otimização de banco de dados.

## Características

- **API REST completa** com controllers para categorias, itens, carrinho e pedidos
- **Autenticação** com Laravel Sanctum
- **Banco de dados** PostgreSQL com Supabase
- **Monitoramento** de performance e logs
- **Otimização** de queries e índices
- **Testes unitários** para controllers e services
- **Documentação** Swagger/OpenAPI
- **Armazenamento** de arquivos com Supabase Storage
- **Integração** WhatsApp para notificações

## Estrutura do Projeto

### Controllers
- `CategoryController` - Gerenciamento de categorias
- `ItemController` - Gerenciamento de itens
- `ItemAdditionalController` - Adicionais de itens
- `CartController` - Carrinho de compras
- `OrderController` - Pedidos
- `MonitoringController` - Monitoramento do sistema
- `DatabaseOptimizationController` - Otimização do banco
- `SwaggerController` - Documentação da API

### Services
- `DatabaseOptimizationService` - Análise de performance
- `LoggingService` - Sistema de logs
- `FileStorageService` - Armazenamento de arquivos
- `WhatsAppService` - Integração WhatsApp

### Models
- `Category` - Categorias de produtos
- `Item` - Produtos/itens
- `ItemAdditional` - Adicionais de produtos
- `Cart` - Carrinho de compras
- `CartItem` - Itens do carrinho
- `Order` - Pedidos
- `OrderItem` - Itens do pedido
- `Company` - Empresas
- `User` - Usuários

## Instalação

1. Clone o repositório
2. Instale as dependências: `composer install`
3. Configure o arquivo `.env` baseado no `.env.example`
4. Execute as migrations: `php artisan migrate`
5. Execute os seeders: `php artisan db:seed`
6. Inicie o servidor: `php artisan serve`

## Testes

Execute os testes unitários:
```bash
php artisan test
```

## Documentação da API

Acesse a documentação Swagger em: `/api/documentation`

## Monitoramento

Acesse o painel de monitoramento em: `/api/monitoring/status`

## Licença

Este projeto é proprietário.