<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ProductController extends Controller
{
    // POST /api/products
    public function store(Request $request)
    {
        $validated = $request->validate([
        'name' => 'required|string|max:255',
        'sku' => 'required|string|max:100|unique:products,sku',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
    ]);

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }


    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $validated['limit'] ?? 10;

        $products = Product::query()
            ->latest('id')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    
    public function sell(Request $request, int $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $quantity = $validated['quantity'];

        try {
            return DB::transaction(function () use ($id, $quantity) {
                $product = Product::whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found.',
                    ], 404);
                }

                if ($product->stock < $quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock.',
                        'data' => [
                            'available_stock' => $product->stock,
                            'requested_quantity' => $quantity,
                        ],
                    ], 422);
                }

                $product->decrement('stock', $quantity);
                $product->refresh();

                return response()->json([
                    'success' => true,
                    'message' => 'Product sold successfully.',
                    'data' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'sold_quantity' => $quantity,
                        'stock' => $product->stock,
                    ],
                ], 200);
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process the sale. Please try again.',
            ], 500);
        }
    }
}
