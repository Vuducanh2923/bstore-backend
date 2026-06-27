<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'sale_percent' => $this->sale_percent,
            'discount_percent' => $this->discount_percent,
            'sale_price' => $this->sale_price,
            'is_sale' => (bool) $this->is_sale,
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
}
