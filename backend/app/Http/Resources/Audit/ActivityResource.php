<?php

declare(strict_types=1);

namespace App\Http\Resources\Audit;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $properties = $this->properties ?? collect();

        return [
            'id' => $this->id,
            'event' => $this->event,
            'description' => $this->description,
            'log_name' => $this->log_name,
            'subject_type' => array_search($this->subject_type, AuditService::SUJETS, true) ?: $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ] : null),
            'changes' => [
                'attributes' => $properties->get('attributes', []),
                'old' => $properties->get('old', []),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
