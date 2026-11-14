<?php
namespace App\Services;

use App\Models\{Offer, Event, StorePost};
use Illuminate\Support\Facades\DB;

class StoreContentDeletion
{
    public function deleteContent(Offer|Event $content): void
    {
        DB::transaction(function () use ($content) {
            StorePost::where('type', $content instanceof Offer ? 'offer' : 'event')
                ->where('source_id', $content->id)->delete();
            $content->delete();
        });
    }

    public function deletePost(StorePost $post): void
    {
        DB::transaction(function () use ($post) {
            $model = match ($post->type) { 'offer' => Offer::class, 'event' => Event::class, default => null };
            $content = $model && $post->source_id
                ? $model::where('organization_id', $post->organization_id)->find($post->source_id) : null;
            if ($content) $this->deleteContent($content);
            else $post->delete();
        });
    }
}
