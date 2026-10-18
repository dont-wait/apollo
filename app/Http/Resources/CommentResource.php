<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'content' => $this->content,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'author' => $this->whenLoaded('user', fn (): array => [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
                'initial' => str($this->user->name)->substr(0, 1)->upper()->toString(),
            ]),
            'replies_count' => (int) ($this->visible_replies_count ?? 0),
        ];
    }
}
