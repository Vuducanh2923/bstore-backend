<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $discountPercent = $this->discount_percent ?? $this->sale_percent;
        $salePrice = $this->resolveSalePrice($discountPercent);
        $isSale = $salePrice !== null
            && ((bool) $this->is_sale || (float) ($discountPercent ?? 0) > 0);

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'warranty_policy_id' => $this->warranty_policy_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'price' => $this->price,
            'sale_percent' => $isSale ? $this->sale_percent : null,
            'discount_percent' => $isSale ? $discountPercent : null,
            'sale_price' => $salePrice,
            'is_sale' => $isSale,
            'status' => $this->status,
            'category' => $this->whenLoaded('category'),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'slug' => $this->brand?->slug,
                'logo' => $this->brand?->logo,
            ]),
            'variants' => $this->whenLoaded('variants'),
            'images' => $this->whenLoaded('images'),
            'warranty_policy' => $this->whenLoaded('warrantyPolicy'),
        ];
    }

    private function resolveSalePrice(mixed $discountPercent): mixed
    {
        if ($this->sale_price !== null) {
            return (float) $this->sale_price > 0 ? $this->sale_price : null;
        }

        if ($discountPercent === null || $discountPercent === '' || (float) $discountPercent <= 0 || (float) $discountPercent >= 100) {
            return null;
        }

        $calculatedSalePrice = (float) $this->price - ((float) $this->price * (float) $discountPercent / 100);

        return $calculatedSalePrice > 0
            ? number_format($calculatedSalePrice, 2, '.', '')
            : null;
    }
}
