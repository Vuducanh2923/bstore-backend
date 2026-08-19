<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{

    // Thực hiện cho mảng.
    public function toArray(Request $request): array
    {
        $hasUsage = (int) $this->used_count > 0 || (int) $this->getAttribute('orders_count') > 0;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->type,
            'discount_value' => $this->value,
            'max_discount_amount' => $this->max_discount_amount,
            'min_order_amount' => $this->min_order_amount,
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_customer' => $this->usage_limit_per_customer,
            'used_count' => $this->used_count,
            'starts_at' => $this->start_date,
            'ends_at' => $this->end_date,
            'status' => $this->status === 'active' && $this->end_date?->isPast() ? 'expired' : $this->status,
            'created_by' => $this->created_by,
            'creator' => $this->getAttribute('creator_context'),
            'can_delete' => ! $hasUsage,
            'can_only_deactivate' => $hasUsage,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
