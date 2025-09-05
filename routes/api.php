<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\SwaggerController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\DatabaseOptimizationController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Menu\CategoryController;
use App\Http\Controllers\Menu\ItemController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\CartController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Documentação da API
Route::prefix('documentation')->group(function () {
    Route::get('/', [SwaggerController::class, 'ui'])->name('swagger.ui');
    Route::get('/json', [SwaggerController::class, 'json'])->name('swagger.json');
    Route::get('/yaml', [SwaggerController::class, 'yaml'])->name('swagger.yaml');
});

// Autenticação
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [ApiAuthController::class, 'register'])->name('register');
    Route::post('login', [ApiAuthController::class, 'login'])->name('login');
    Route::post('logout', [ApiAuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
    Route::post('refresh', [ApiAuthController::class, 'refresh'])->middleware('auth:sanctum')->name('refresh');
    Route::get('me', [ApiAuthController::class, 'me'])->middleware('auth:sanctum')->name('me');
    Route::post('forgot-password', [ApiAuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('reset-password', [ApiAuthController::class, 'resetPassword'])->name('reset-password');
    Route::post('verify-email', [ApiAuthController::class, 'verifyEmail'])->name('verify-email');
    Route::post('resend-verification', [ApiAuthController::class, 'resendVerification'])->name('resend-verification');
});

// Upload de arquivos
Route::prefix('upload')->middleware('auth:sanctum')->name('upload.')->group(function () {
    Route::post('image', [FileUploadController::class, 'uploadImage'])->name('image');
    Route::post('document', [FileUploadController::class, 'uploadDocument'])->name('document');
    Route::delete('file/{filename}', [FileUploadController::class, 'deleteFile'])->name('delete');
    Route::get('file/{filename}', [FileUploadController::class, 'getFile'])->name('get');
});

// Rotas públicas (sem autenticação)
Route::prefix('public')->name('public.')->group(function () {
    // Rotas de monitoramento
    Route::prefix('monitoring')->group(function () {
        Route::get('/health', [MonitoringController::class, 'health']);
        Route::get('/metrics', [MonitoringController::class, 'metrics']);
        Route::get('/logs', [MonitoringController::class, 'logs']);
        Route::post('/test-alert', [MonitoringController::class, 'testAlert']);
    });

    // Rotas de otimização do banco de dados
    Route::prefix('database')->group(function () {
        Route::get('analyze', [DatabaseOptimizationController::class, 'analyze']);
        Route::get('slow-queries', [DatabaseOptimizationController::class, 'getSlowQueries']);
        Route::get('table-stats', [DatabaseOptimizationController::class, 'getTableStatistics']);
        Route::get('size', [DatabaseOptimizationController::class, 'getDatabaseSize']);
        Route::get('recommendations', [DatabaseOptimizationController::class, 'getRecommendations']);
        Route::post('optimize-table', [DatabaseOptimizationController::class, 'optimizeTable']);
        Route::post('create-indexes', [DatabaseOptimizationController::class, 'createRecommendedIndexes']);
    });

// Incluir rotas específicas do WhatsApp
require __DIR__.'/api_whatsapp.php';
    });
    
    // Empresas (rotas públicas para listagem)
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index'); // Listar empresas ativas
        Route::get('/{company:slug}', [CompanyController::class, 'show'])->name('show'); // Detalhes da empresa
        Route::get('/{company:slug}/menu', [CompanyController::class, 'getMenu'])->name('menu'); // Cardápio da empresa
        Route::get('/{company:slug}/categories', [CategoryController::class, 'getByCompany'])->name('categories'); // Categorias da empresa
        Route::get('/{company:slug}/items', [ItemController::class, 'getByCompany'])->name('items'); // Itens da empresa
        Route::get('/{company:slug}/reviews', [ReviewController::class, 'getByCompany'])->name('reviews'); // Avaliações da empresa
    });
    
    // Cardápio (rotas públicas)
    Route::prefix('menu')->name('menu.')->group(function () {
        Route::get('categories/{companyId}', [CategoryController::class, 'getByCompany'])->name('categories');
        Route::get('items/{companyId}', [ItemController::class, 'getByCompany'])->name('items');
        Route::get('items/{id}', [ItemController::class, 'show'])->name('item.show');
        Route::get('featured/{companyId}', [ItemController::class, 'getFeatured'])->name('featured');
    });
    
    // Rotas públicas de produtos/itens
    Route::prefix('items')->name('items.')->group(function () {
        Route::get('/{item}', [ItemController::class, 'show'])->name('show');
        Route::get('/{item}/reviews', [ReviewController::class, 'getByProduct'])->name('reviews');
    });
    
    // Rotas públicas de categorias
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
        Route::get('/{category}/items', [ItemController::class, 'getByCategory'])->name('items');
    });
    
    // Rotas protegidas (requerem autenticação)
    Route::middleware('auth:sanctum')->group(function () {
        
        // Usuários
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/{user}', [UserController::class, 'show'])->name('show');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::get('/{user}/orders', [OrderController::class, 'getUserOrders'])->name('orders');
            Route::get('/{user}/reviews', [ReviewController::class, 'getUserReviews'])->name('reviews');
            Route::get('/{user}/notifications', [NotificationController::class, 'getUserNotifications'])->name('notifications');
        });
        
        // Empresas (rotas protegidas)
        Route::prefix('companies')->name('companies.')->group(function () {
            Route::post('/', [CompanyController::class, 'store'])->name('store');
            Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
            Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');
            Route::get('/{company}/dashboard', [DashboardController::class, 'getCompanyDashboard'])->name('dashboard');
            Route::get('/{company}/orders', [OrderController::class, 'getCompanyOrders'])->name('orders');
            Route::get('/{company}/stats', [DashboardController::class, 'getCompanyStats'])->name('stats');
            Route::post('/{company}/upload-logo', [CompanyController::class, 'uploadLogo'])->name('upload-logo');
            Route::post('/{company}/upload-banner', [CompanyController::class, 'uploadBanner'])->name('upload-banner');
        });
        
        // Categorias (rotas protegidas)
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
            Route::post('/{category}/upload-image', [CategoryController::class, 'uploadImage'])->name('upload-image');
        });
        
        // Itens/Produtos (rotas protegidas)
        Route::prefix('items')->name('items.')->group(function () {
            Route::post('/', [ItemController::class, 'store'])->name('store');
            Route::put('/{item}', [ItemController::class, 'update'])->name('update');
            Route::delete('/{item}', [ItemController::class, 'destroy'])->name('destroy');
            Route::post('/{item}/upload-images', [ItemController::class, 'uploadImages'])->name('upload-images');
            Route::delete('/{item}/images/{image}', [ItemController::class, 'deleteImage'])->name('delete-image');
            Route::post('/{item}/toggle-availability', [ItemController::class, 'toggleAvailability'])->name('toggle-availability');
            Route::post('/{item}/duplicate', [ItemController::class, 'duplicate'])->name('duplicate');
        });
        
        // Carrinho de compras
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index'); // Ver carrinho
            Route::post('add', [CartController::class, 'addItem'])->name('add'); // Adicionar item
            Route::put('update/{itemId}', [CartController::class, 'updateItem'])->name('update'); // Atualizar quantidade
            Route::delete('remove/{itemId}', [CartController::class, 'removeItem'])->name('remove'); // Remover item
            Route::delete('clear', [CartController::class, 'clear'])->name('clear'); // Limpar carrinho
            Route::post('apply-coupon', [CartController::class, 'applyCoupon'])->name('apply-coupon'); // Aplicar cupom
            Route::delete('remove-coupon', [CartController::class, 'removeCoupon'])->name('remove-coupon'); // Remover cupom
            Route::get('summary', [CartController::class, 'getSummary'])->name('summary'); // Resumo do carrinho
        });
        
        // Pedidos
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::post('/', [OrderController::class, 'store'])->name('store');
            Route::get('/{order}', [OrderController::class, 'show'])->name('show');
            Route::put('/{order}', [OrderController::class, 'update'])->name('update');
            Route::delete('/{order}', [OrderController::class, 'destroy'])->name('destroy');
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
            Route::post('/{order}/confirm', [OrderController::class, 'confirm'])->name('confirm');
            Route::post('/{order}/prepare', [OrderController::class, 'prepare'])->name('prepare');
            Route::post('/{order}/ready', [OrderController::class, 'ready'])->name('ready');
            Route::post('/{order}/deliver', [OrderController::class, 'deliver'])->name('deliver');
            Route::post('/{order}/complete', [OrderController::class, 'complete'])->name('complete');
            Route::get('/{order}/tracking', [OrderController::class, 'getTracking'])->name('tracking');
            Route::get('/{order}/receipt', [OrderController::class, 'getReceipt'])->name('receipt');
        });
        
        // Pagamentos
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('/process', [PaymentController::class, 'process'])->name('process');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
            Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->name('refund');
            Route::get('/methods/available', [PaymentController::class, 'getAvailableMethods'])->name('methods');
        });
        
        // Entrega
        Route::prefix('delivery')->name('delivery.')->group(function () {
            Route::get('/zones', [DeliveryController::class, 'getZones'])->name('zones');
            Route::post('/zones', [DeliveryController::class, 'createZone'])->name('create-zone');
            Route::put('/zones/{zone}', [DeliveryController::class, 'updateZone'])->name('update-zone');
            Route::delete('/zones/{zone}', [DeliveryController::class, 'deleteZone'])->name('delete-zone');
            Route::post('/calculate-fee', [DeliveryController::class, 'calculateFee'])->name('calculate-fee');
            Route::get('/drivers', [DeliveryController::class, 'getDrivers'])->name('drivers');
            Route::post('/assign-driver', [DeliveryController::class, 'assignDriver'])->name('assign-driver');
        });
        
        // Cupons
        Route::prefix('coupons')->name('coupons.')->group(function () {
            Route::get('/', [CouponController::class, 'index'])->name('index');
            Route::post('/', [CouponController::class, 'store'])->name('store');
            Route::get('/{coupon}', [CouponController::class, 'show'])->name('show');
            Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
            Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
            Route::post('/validate', [CouponController::class, 'validate'])->name('validate');
            Route::get('/available/{company}', [CouponController::class, 'getAvailable'])->name('available');
        });
        
        // Avaliações
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/', [ReviewController::class, 'index'])->name('index');
            Route::post('/', [ReviewController::class, 'store'])->name('store');
            Route::get('/{review}', [ReviewController::class, 'show'])->name('show');
            Route::put('/{review}', [ReviewController::class, 'update'])->name('update');
            Route::delete('/{review}', [ReviewController::class, 'destroy'])->name('destroy');
            Route::post('/{review}/like', [ReviewController::class, 'like'])->name('like');
            Route::delete('/{review}/unlike', [ReviewController::class, 'unlike'])->name('unlike');
            Route::post('/{review}/report', [ReviewController::class, 'report'])->name('report');
        });
        
        // Notificações
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
            Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
            Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
            Route::delete('/clear-all', [NotificationController::class, 'clearAll'])->name('clear-all');
            Route::get('/unread/count', [NotificationController::class, 'getUnreadCount'])->name('unread-count');
        });
        
        // Dashboard
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/overview', [DashboardController::class, 'getOverview'])->name('overview');
            Route::get('/stats', [DashboardController::class, 'getStats'])->name('stats');
            Route::get('/recent-orders', [DashboardController::class, 'getRecentOrders'])->name('recent-orders');
            Route::get('/top-products', [DashboardController::class, 'getTopProducts'])->name('top-products');
            Route::get('/revenue-chart', [DashboardController::class, 'getRevenueChart'])->name('revenue-chart');
            Route::get('/orders-chart', [DashboardController::class, 'getOrdersChart'])->name('orders-chart');
        });
        
        // Perfil do usuário (rotas adicionais)
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('orders', [OrderController::class, 'getUserOrders'])->name('orders'); // Histórico de pedidos
            Route::get('stats', [AuthController::class, 'getUserStats'])->name('stats'); // Estatísticas do usuário
        });
    });
    
    // Rotas administrativas (futuro - para painel admin)
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->name('admin.')->group(function () {
        // Será implementado posteriormente
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Admin dashboard - Em desenvolvimento']);
        })->name('dashboard');
    });
    
    // Rotas para empresas (futuro - para painel da empresa)
    Route::prefix('company')->middleware(['auth:sanctum', 'company'])->name('company.')->group(function () {
        // Será implementado posteriormente
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Company dashboard - Em desenvolvimento']);
        })->name('dashboard');
    });
});

// Rota de fallback para API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint não encontrado',
        'error' => 'A rota solicitada não existe nesta API'
    ], 404);
});

// Rota de health check
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API funcionando corretamente',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

// Rota de informações da API
Route::get('info', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'name' => 'StarAnotado API',
            'version' => '1.0.0',
            'description' => 'API para sistema de delivery reconstruído em código limpo',
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'database' => 'PostgreSQL (Supabase)',
            'authentication' => 'Laravel Sanctum',
        ]
    ]);
});