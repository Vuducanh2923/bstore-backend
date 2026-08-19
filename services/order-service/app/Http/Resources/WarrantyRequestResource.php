<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarrantyRequestResource extends JsonResource
{

    // Thực hiện cho mảng.
    public function toArray(Request $request): array
    {
        $order = $this->whenLoaded('order');
        $item = $this->whenLoaded('orderItem');

        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'customer_id' => $this->user_id,
            'customer' => $this->getAttribute('customer_context'),
            'order_id' => $this->order_id,
            'order' => $order ? [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'status' => $order->status,
                'delivered_at' => $order->delivered_at,
            ] : null,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'product' => $this->getAttribute('product_context') ?: ($item ? [
                'id' => $this->product_id,
                'variant_id' => $item->product_variant_id,
                'name' => $item->product_name,
                'image_url' => $item->product_image,
                'color' => $item->color,
                'ram' => $item->ram,
                'storage' => $item->storage,
            ] : null),
            'warranty_policy' => $this->getAttribute('warranty_policy_context'),
            'reason' => $this->reason,
            'description' => $this->description,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'processing_note' => $this->processing_note ?? $this->admin_note,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at,
            'completed_at' => $this->completed_at,
            'warranty_start_date' => $this->warranty_start_date?->toDateString(),
            'warranty_end_date' => $this->warranty_end_date?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
