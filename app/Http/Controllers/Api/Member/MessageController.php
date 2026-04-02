<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    use ApiResponse;

    /**
     * Inbox — messages received.
     */
    public function inbox(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = Message::inbox($userId)
            ->with('sender:id,first_name,last_name,avatar')
            ->when($request->search, fn($q, $s) => $q->where(function ($sq) use ($s) {
                $sq->where('subject', 'like', "%{$s}%")
                   ->orWhere('body', 'like', "%{$s}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        $transformed = $messages->through(fn($m) => [
            'id'      => $m->id,
            'subject' => $m->subject,
            'body_preview' => \Illuminate\Support\Str::limit(strip_tags($m->body), 100),
            'is_read' => $m->isRead(),
            'sender'  => [
                'id'        => $m->sender->id,
                'full_name' => $m->sender->full_name,
                'avatar'    => $m->sender->avatar,
            ],
            'created_at' => $m->created_at->toIso8601String(),
        ]);

        return $this->paginated($transformed);
    }

    /**
     * Sent messages.
     */
    public function sent(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = Message::sent($userId)
            ->with('receiver:id,first_name,last_name,avatar')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        $transformed = $messages->through(fn($m) => [
            'id'      => $m->id,
            'subject' => $m->subject,
            'body_preview' => \Illuminate\Support\Str::limit(strip_tags($m->body), 100),
            'receiver' => [
                'id'        => $m->receiver->id,
                'full_name' => $m->receiver->full_name,
                'avatar'    => $m->receiver->avatar,
            ],
            'created_at' => $m->created_at->toIso8601String(),
        ]);

        return $this->paginated($transformed);
    }

    /**
     * View a single message.
     */
    public function show(Request $request, Message $message): JsonResponse
    {
        $userId = $request->user()->id;

        if ($message->sender_id !== $userId && $message->receiver_id !== $userId) {
            return $this->forbidden('You cannot view this message.');
        }

        // Mark as read if receiver is viewing
        if ($message->receiver_id === $userId && !$message->isRead()) {
            $message->update(['read_at' => now()]);
        }

        $message->load(['sender:id,first_name,last_name,avatar', 'receiver:id,first_name,last_name,avatar']);

        return $this->success([
            'id'      => $message->id,
            'subject' => $message->subject,
            'body'    => $message->body,
            'is_read' => $message->isRead(),
            'read_at' => $message->read_at?->toIso8601String(),
            'sender'  => [
                'id'        => $message->sender->id,
                'full_name' => $message->sender->full_name,
                'avatar'    => $message->sender->avatar,
            ],
            'receiver' => [
                'id'        => $message->receiver->id,
                'full_name' => $message->receiver->full_name,
                'avatar'    => $message->receiver->avatar,
            ],
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }

    /**
     * Send a message.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'exists:users,id', 'different:' . $request->user()->id],
            'subject'     => ['nullable', 'string', 'max:255'],
            'body'        => ['required', 'string', 'max:5000'],
        ]);

        $message = Message::create([
            'sender_id'   => $request->user()->id,
            'receiver_id' => $validated['receiver_id'],
            'subject'     => $validated['subject'],
            'body'        => $validated['body'],
        ]);

        return $this->created([
            'id' => $message->id,
        ], 'Message sent.');
    }

    /**
     * Delete a message (soft delete for the current user).
     */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        $userId = $request->user()->id;

        if ($message->sender_id === $userId) {
            $message->update(['sender_deleted' => true]);
        } elseif ($message->receiver_id === $userId) {
            $message->update(['receiver_deleted' => true]);
        } else {
            return $this->forbidden('You cannot delete this message.');
        }

        // Hard delete if both deleted
        if ($message->sender_deleted && $message->receiver_deleted) {
            $message->delete();
        }

        return $this->success(null, 'Message deleted.');
    }

    /**
     * Unread count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Message::unread($request->user()->id)->count();

        return $this->success(['unread_count' => $count]);
    }
}
