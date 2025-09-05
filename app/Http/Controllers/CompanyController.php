<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Lista empresas com filtros e paginação
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Company::query();

            // Filtros
            if ($request->filled('active_only')) {
                $query->active();
            }

            if ($request->filled('open_only')) {
                $query->open();
            }

            if ($request->filled('accepting_orders')) {
                $query->acceptingOrders();
            }

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('category')) {
                $query->withCategory($request->category);
            }

            if ($request->filled('delivery_fee_max')) {
                $query->where('delivery_fee', '<=', $request->delivery_fee_max);
            }

            if ($request->filled('min_order_value_max')) {
                $query->where('min_order_value', '<=', $request->min_order_value_max);
            }

            if ($request->filled('rating_min')) {
                $query->where('rating', '>=', $request->rating_min);
            }

            // Ordenação
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            
            if (in_array($sortBy, ['name', 'rating', 'delivery_fee', 'min_order_value', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('name');
            }

            $companies = $query->paginate($request->get('per_page', 20));

            return $this->successResponse($companies, 'Empresas listadas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao listar empresas: ' . $e->getMessage());
        }
    }

    /**
     * Exibe uma empresa específica
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $cacheKey = "company_{$id}_details";
            
            $company = Cache::remember($cacheKey, 600, function () use ($id) {
                return Company::with([
                    'activeCategories' => function ($q) {
                        $q->withActiveItems()->ordered();
                    },
                    'activeCategories.activeItems' => function ($q) {
                        $q->available()->ordered()->limit(5);
                    }
                ])->findOrFail($id);
            });

            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            // Adicionar estatísticas se solicitado
            if ($request->boolean('include_stats')) {
                $company->stats = $company->getStats();
            }

            // Adicionar horários de funcionamento se solicitado
            if ($request->boolean('include_hours')) {
                $company->operating_hours = $company->getOperatingHours();
            }

            return $this->successResponse($company, 'Empresa encontrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Empresa não encontrada: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Busca empresas por localização
     */
    public function searchByLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:50',
            'category' => 'nullable|string',
            'min_rating' => 'nullable|numeric|between:0,5',
            'max_delivery_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->get('radius', 10); // km

            $query = Company::active()
                ->acceptingOrders()
                ->selectRaw(
                    '*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance',
                    [$latitude, $longitude, $latitude]
                )
                ->having('distance', '<', $radius);

            // Filtros adicionais
            if ($request->filled('category')) {
                $query->withCategory($request->category);
            }

            if ($request->filled('min_rating')) {
                $query->where('rating', '>=', $request->min_rating);
            }

            if ($request->filled('max_delivery_fee')) {
                $query->where('delivery_fee', '<=', $request->max_delivery_fee);
            }

            $companies = $query->orderBy('distance')
                ->paginate($request->get('per_page', 20));

            return $this->successResponse($companies, 'Empresas próximas encontradas');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro na busca por localização: ' . $e->getMessage());
        }
    }

    /**
     * Obtém o cardápio completo de uma empresa
     */
    public function menu($id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $cacheKey = "company_{$id}_menu";
            
            $menu = Cache::remember($cacheKey, 600, function () use ($company) {
                return $company->activeCategories()
                    ->withActiveItems()
                    ->with([
                        'activeItems' => function ($q) {
                            $q->available()->ordered();
                        },
                        'activeItems.activeAdditionals' => function ($q) {
                            $q->ordered();
                        }
                    ])
                    ->ordered()
                    ->get();
            });

            return $this->successResponse($menu, 'Cardápio obtido com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter cardápio: ' . $e->getMessage());
        }
    }

    /**
     * Busca no cardápio de uma empresa
     */
    public function searchMenu(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2',
            'category_id' => 'nullable|exists:categories,id',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'max_preparation_time' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $query = Item::whereHas('category', function ($q) use ($id) {
                $q->where('company_id', $id)->active();
            })
            ->active()
            ->available()
            ->search($request->search)
            ->with([
                'category:id,name',
                'activeAdditionals' => function ($q) {
                    $q->ordered();
                }
            ]);

            // Filtros adicionais
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled(['min_price', 'max_price'])) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            if ($request->filled('max_preparation_time')) {
                $query->preparationTime($request->max_preparation_time);
            }

            $items = $query->ordered()
                ->paginate($request->get('per_page', 20));

            return $this->successResponse($items, 'Busca no cardápio realizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro na busca: ' . $e->getMessage());
        }
    }

    /**
     * Obtém itens em destaque de uma empresa
     */
    public function featuredItems($id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $cacheKey = "company_{$id}_featured_items";
            
            $items = Cache::remember($cacheKey, 600, function () use ($company) {
                return Item::whereHas('category', function ($q) use ($company) {
                    $q->where('company_id', $company->id)->active();
                })
                ->active()
                ->available()
                ->featured()
                ->with([
                    'category:id,name',
                    'activeAdditionals' => function ($q) {
                        $q->ordered();
                    }
                ])
                ->ordered()
                ->limit(20)
                ->get();
            });

            return $this->successResponse($items, 'Itens em destaque obtidos com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter itens em destaque: ' . $e->getMessage());
        }
    }

    /**
     * Obtém itens em promoção de uma empresa
     */
    public function promotionalItems($id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $cacheKey = "company_{$id}_promotional_items";
            
            $items = Cache::remember($cacheKey, 600, function () use ($company) {
                return Item::whereHas('category', function ($q) use ($company) {
                    $q->where('company_id', $company->id)->active();
                })
                ->active()
                ->available()
                ->onPromotion()
                ->with([
                    'category:id,name',
                    'activeAdditionals' => function ($q) {
                        $q->ordered();
                    }
                ])
                ->ordered()
                ->get();
            });

            return $this->successResponse($items, 'Itens em promoção obtidos com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter itens em promoção: ' . $e->getMessage());
        }
    }

    /**
     * Verifica status da empresa (aberta/fechada)
     */
    public function checkStatus($id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $status = [
                'is_open' => $company->isOpen(),
                'is_accepting_orders' => $company->isAcceptingOrders(),
                'next_opening' => $company->getNextOpening(),
                'estimated_delivery_time' => $company->getEstimatedDeliveryTime(),
                'current_load' => $company->getCurrentOrderLoad(),
            ];

            return $this->successResponse($status, 'Status da empresa verificado');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao verificar status: ' . $e->getMessage());
        }
    }

    /**
     * Calcula taxa de entrega
     */
    public function calculateDeliveryFee(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'order_value' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $deliveryFee = $company->calculateDeliveryFee(
                $request->latitude,
                $request->longitude,
                $request->get('order_value', 0)
            );

            $result = [
                'delivery_fee' => $deliveryFee,
                'free_delivery_threshold' => $company->free_delivery_threshold,
                'is_free_delivery' => $deliveryFee == 0,
                'estimated_time' => $company->getEstimatedDeliveryTime(),
            ];

            return $this->successResponse($result, 'Taxa de entrega calculada');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao calcular taxa: ' . $e->getMessage());
        }
    }

    /**
     * Obtém estatísticas de uma empresa
     */
    public function stats($id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $cacheKey = "company_{$id}_stats";
            
            $stats = Cache::remember($cacheKey, 3600, function () use ($company) {
                return $company->getStats();
            });

            return $this->successResponse($stats, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter estatísticas: ' . $e->getMessage());
        }
    }

    /**
     * Limpa cache da empresa
     */
    protected function clearCompanyCache($companyId): void
    {
        $cacheKeys = [
            "company_{$companyId}_details",
            "company_{$companyId}_menu",
            "company_{$companyId}_featured_items",
            "company_{$companyId}_promotional_items",
            "company_{$companyId}_stats",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}