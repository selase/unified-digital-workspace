<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\CmsCore\Models\MenuItem
 */
final class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'label' => $this->label,
            'url' => $this->url,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
