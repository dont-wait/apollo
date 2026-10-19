<?php

namespace App\Http\Resources;

use App\Models\Comment;
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
            'parent_id' => $this->parent_id !== null ? (int) $this->parent_id : null,
            'content' => $this->status === Comment::STATUS_DELETED
                ? '[Comment deleted]'
                : $this->content,
            'status' => $this->status,
            'version' => (int) $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'author' => $this->when($this->relationLoaded('user'), function (): array {
                if ($this->status === Comment::STATUS_DELETED || $this->user === null) {
                    return [
                        'id' => null,
                        'name' => 'Deleted user',
                        'avatar_url' => null,
                        'initial' => 'D',
                    ];
                }

                return [
                    'id' => (int) $this->user->id,
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->avatar_url,
                    'initial' => str($this->user->name)->substr(0, 1)->upper()->toString(),
                ];
            }),
            'replies_count' => (int) ($this->visible_replies_count ?? 0),
        ];
    }
}
