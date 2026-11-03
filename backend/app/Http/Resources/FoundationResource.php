<?php

namespace App\Http\Resources;

use App\FoundationCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoundationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $config = FoundationCatalog::get($request->route('resource'));
        $data = ['id' => $this->id];
        foreach ($config['fields'] as $field) {
            $value = $field === 'teacher_ids' ? $this->teachers->pluck('id')->all() : $this->{$field};
            $data[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }
        $data['labels'] = [];
        foreach ($config['relations'] as $relation) {
            $data['labels'][$relation] = $relation === 'teachers'
                ? $this->teachers->pluck('name')->implode(', ') : $this->{$relation}?->name;
        }
        $data['updated_at'] = $this->updated_at?->toIso8601String();

        return $data;
    }
}
