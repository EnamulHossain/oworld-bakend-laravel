<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorePost;
use App\Models\StorePostComment;
use App\Models\StorePostLike;
use Illuminate\Http\Request;

class StorePostInteractionController extends Controller
{
    public function comments(StorePost $post)
    {
        $comments = StorePostComment::with('user:id,username,full_name,first_name,last_name,avatar')
            ->where('store_post_id', $post->id)->latest()->get()->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'author' => $this->commentAuthor($comment),
                'avatar' => $comment->user?->avatar,
                'created_at' => $comment->created_at,
            ]);

        return response()->json(['comments' => $comments, 'count' => $comments->count()]);
    }

    public function status(Request $request, StorePost $post)
    {
        return response()->json([
            'liked' => StorePostLike::where(['store_post_id' => $post->id, 'user_id' => $request->user()->id])->exists(),
            'likes_count' => StorePostLike::where('store_post_id', $post->id)->count(),
            'comments_count' => StorePostComment::where('store_post_id', $post->id)->count(),
        ]);
    }

    public function toggleLike(Request $request, StorePost $post)
    {
        $query = StorePostLike::where(['store_post_id' => $post->id, 'user_id' => $request->user()->id]);
        $liked = !$query->exists();
        $liked ? StorePostLike::create(['store_post_id' => $post->id, 'user_id' => $request->user()->id]) : $query->delete();

        return response()->json(['liked' => $liked, 'likes_count' => StorePostLike::where('store_post_id', $post->id)->count()]);
    }

    public function addComment(Request $request, StorePost $post)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $comment = StorePostComment::create(['store_post_id' => $post->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])]);
        $comment->load('user:id,username,full_name,first_name,last_name,avatar');

        return response()->json(['comment' => [
            'id' => $comment->id, 'body' => $comment->body,
            'author' => $this->commentAuthor($comment),
            'avatar' => $comment->user?->avatar, 'created_at' => $comment->created_at,
        ]], 201);
    }

    private function commentAuthor(StorePostComment $comment): string
    {
        $fullName = trim((string) ($comment->user?->full_name ?? ''));
        $firstAndLastName = trim(
            ($comment->user?->first_name ?? '').' '.($comment->user?->last_name ?? '')
        );

        return $fullName ?: ($firstAndLastName ?: ($comment->user?->username ?? 'User'));
    }
}
