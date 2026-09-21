<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner-facing view of an invitation, including the shareable accept link.
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter ? [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ] : null),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accept_url' => $this->acceptUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
