<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Lista categorias de uma empresa
     */
    public function index(Request $request, $companyId): JsonResponse
    {
        try {
            // Verificar se a empresa existe e está ativa
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $query = Category::byCompany($companyId)
                ->with(['activeItems' => function ($q) {
                    $q->available()->ordered();
                }]);

            // Filtros
            if ($request->filled('active_only')) {
                $query->active();
            }

            if ($request->filled('with_items_only')) {
                $query->withActiveItems();
            }

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Ordenação
            $query->ordered();

            $categories = $query->get();

            return $this->successResponse($categories, 'Categorias listadas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao listar categorias: ' . $e->getMessage());
        }
    }

    /**
     * Exibe uma categoria específica com seus itens
     */
    public function show(Request $request, $companyId, $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $cacheKey = "category_{$id}_items";
            
            $category = Cache::remember($cacheKey, 300, function () use ($companyId, $id) {
                return Category::byCompany($companyId)
                    ->with([
                        'activeItems' => function ($q) {
                            $q->available()->ordered();
                        },
                        'activeItems.activeAdditionals' => function ($q) {
                            $q->ordered();
                        }
                    ])
                    ->findOrFail($id);
            });

            // Adicionar estatísticas se solicitado
            if ($request->boolean('include_stats')) {
                $category->stats = $category->getStats();
            }

            return $this->successResponse($category, 'Categoria encontrada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Categoria não encontrada: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Obtém itens de uma categoria
     */
    public function items(Request $request, $companyId, $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $category = Category::byCompany($companyId)->findOrFail($id);
            
            if (!$category->is_active) {
                return $this->errorResponse('Categoria não está ativa', 404);
            }

            $query = $category->activeItems()
                ->with(['activeAdditionals' => function ($q) {
                    $q->ordered();
                }]);

            // Filtros
            if ($request->filled('available_only')) {
                $query->available();
            }

            if ($request->filled('featured_only')) {
                $query->featured();
            }

            if ($request->filled('on_promotion')) {
                $query->onPromotion();
            }

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled(['min_price', 'max_price'])) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            if ($request->filled('max_preparation_time')) {
                $query->preparationTime($request->max_preparation_time);
            }

            if ($request->filled('tags')) {
                $tags = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
                $query->withTags($tags);
            }

            // Ordenação
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortOrder = $request->get('sort_order', 'asc');
            
            if (in_array($sortBy, ['name', 'price', 'sort_order', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->ordered();
            }

            $items = $query->paginate($request->get('per_page', 20));

            return $this->successResponse($items, 'Itens da categoria listados com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao listar itens: ' . $e->getMessage());
        }
    }

    /**
     * Obtém itens em destaque de uma categoria
     */
    public function featuredItems($companyId, $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $category = Category::byCompany($companyId)->findOrFail($id);
            
            if (!$category->is_active) {
                return $this->errorResponse('Categoria não está ativa', 404);
            }

            $cacheKey = "category_{$id}_featured_items";
            
            $items = Cache::remember($cacheKey, 300, function () use ($category) {
                return $category->featuredItems()
                    ->available()
                    ->with(['activeAdditionals' => function ($q) {
                        $q->ordered();
                    }])
                    ->ordered()
                    ->get();
            });

            return $this->successResponse($items, 'Itens em destaque obtidos com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter itens em destaque: ' . $e->getMessage());
        }
    }

    /**
     * Obtém itens em promoção de uma categoria
     */
    public function promotionalItems($companyId, $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $category = Category::byCompany($companyId)->findOrFail($id);
            
            if (!$category->is_active) {
                return $this->errorResponse('Categoria não está ativa', 404);
            }

            $cacheKey = "category_{$id}_promotional_items";
            
            $items = Cache::remember($cacheKey, 300, function () use ($category) {
                return $category->activeItems()
                    ->available()
                    ->onPromotion()
                    ->with(['activeAdditionals' => function ($q) {
                        $q->ordered();
                    }])
                    ->ordered()
                    ->get();
            });

            return $this->successResponse($items, 'Itens em promoção obtidos com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter itens em promoção: ' . $e->getMessage());
        }
    }

    /**
     * Busca itens dentro de uma categoria
     */
    public function searchItems(Request $request, $companyId, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'max_preparation_time' => 'nullable|integer|min:1',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $category = Category::byCompany($companyId)->findOrFail($id);
            
            if (!$category->is_active) {
                return $this->errorResponse('Categoria não está ativa', 404);
            }

            $query = $category->activeItems()
                ->available()
                ->search($request->search)
                ->with(['activeAdditionals' => function ($q) {
                    $q->ordered();
                }]);

            // Filtros adicionais
            if ($request->filled(['min_price', 'max_price'])) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            if ($request->filled('max_preparation_time')) {
                $query->preparationTime($request->max_preparation_time);
            }

            if ($request->filled('tags')) {
                $query->withTags($request->tags);
            }

            $items = $query->ordered()
                ->paginate($request->get('per_page', 20));

            return $this->successResponse($items, 'Busca realizada com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro na busca: ' . $e->getMessage());
        }
    }

    /**
     * Obtém os itens mais vendidos de uma categoria
     */
    public function topSellingItems($companyId, $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $category = Category::byCompany($companyId)->findOrFail($id);
            
            if (!$category->is_active) {
                return $this->errorResponse('Categoria não está ativa', 404);
            }

            $cacheKey = "category_{$id}_top_selling";
            
            $items = Cache::remember($cacheKey, 3600, function () use ($category) {
                return $category->getTopSellingItems(10);
            });

            return $this->successResponse($items, 'Itens mais vendidos obtidos com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter itens mais vendidos: ' . $e->getMessage());
        }
    }

    /**
     * Obtém estatísticas de uma categoria
     */
    public function stats($companyId, $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $category = Category::byCompany($companyId)->findOrFail($id);
            
            if (!$category->is_active) {
                return $this->errorResponse('Categoria não está ativa', 404);
            }

            $cacheKey = "category_{$id}_stats";
            
            $stats = Cache::remember($cacheKey, 3600, function () use ($category) {
                return $category->getStats();
            });

            return $this->successResponse($stats, 'Estatísticas obtidas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter estatísticas: ' . $e->getMessage());
        }
    }

    /**
     * Obtém categorias com contagem de itens
     */
    public function withItemCounts($companyId): JsonResponse
    {
        try {
            $company = Company::findOrFail($companyId);
            
            if (!$company->is_active) {
                return $this->errorResponse('Empresa não está ativa', 404);
            }

            $cacheKey = "company_{$companyId}_categories_with_counts";
            
            $categories = Cache::remember($cacheKey, 600, function () use ($companyId) {
                return Category::byCompany($companyId)
                    ->active()
                    ->withActiveItems()
                    ->withCount([
                        'items',
                        'activeItems',
                        'availableItems',
                        'featuredItems'
                    ])
                    ->ordered()
                    ->get()
                    ->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'description' => $category->description,
                            'image_url' => $category->image_url,
                            'sort_order' => $category->sort_order,
                            'items_count' => $category->items_count,
                            'active_items_count' => $category->active_items_count,
                            'available_items_count' => $category->available_items_count,
                            'featured_items_count' => $category->featured_items_count,
                        ];
                    });
            });

            return $this->successResponse($categories, 'Categorias com contagens obtidas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao obter categorias: ' . $e->getMessage());
        }
    }

    /**
     * Limpa cache da categoria
     */
    protected function clearCategoryCache($categoryId): void
    {
        $cacheKeys = [
            "category_{$categoryId}_items",
            "category_{$categoryId}_featured_items",
            "category_{$categoryId}_promotional_items",
            "category_{$categoryId}_top_selling",
            "category_{$categoryId}_stats",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}