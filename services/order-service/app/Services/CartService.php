<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(private readonly CatalogPricingService $catalogPricingService) {}

    public function create(array $data): Cart
    {
        return DB::connection('bstore_order')->transaction(function () use ($data) {
            $items = $this->catalogPricingService->applyCurrentPrices($data['items'] ?? []);
            unset($data['items']);

            $cart = Cart::create($data);

            foreach ($items as $item) {
                $item['cart_id'] = $cart->id;
                $item['subtotal'] = $this->subtotal($item);

                CartItem::create($item);
            }

            return $cart->fresh('items');
        });
    }

    private function subtotal(array $item): float
    {
        return (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
    }
}
