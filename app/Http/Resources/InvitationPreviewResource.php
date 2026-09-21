<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the invitee sees on the public accept page.
 */
class InvitationPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'workspace' => ['name' => $this->tenant->name],
            'email' => $this->email,
            'invited_by' => $this->inviter?->name,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'status' => $this->status(),
        ];
    }
}
