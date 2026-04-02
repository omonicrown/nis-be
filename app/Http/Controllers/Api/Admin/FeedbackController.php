<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Feedback::with('user:id,first_name,last_name,email')
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->when($request->status, fn($q, $s) => $q->byStatus($s))
            ->orderByDesc('created_at');

        $feedbacks = $query->paginate($request->per_page ?? 20);

        $transformed = $feedbacks->through(fn($f) => [
            'id'             => $f->id,
            'type'           => $f->type,
            'subject'        => $f->subject,
            'body'           => $f->body,
            'status'         => $f->status,
            'admin_response' => $f->admin_response,
            'responded_at'   => $f->responded_at?->toIso8601String(),
            'user'           => $f->user ? [
                'id'        => $f->user->id,
                'full_name' => $f->user->full_name,
                'email'     => $f->user->email,
            ] : null,
            'created_at' => $f->created_at->toIso8601String(),
        ]);

        return $this->paginated($transformed);
    }

    public function show(Feedback $feedback): JsonResponse
    {
        $feedback->load(['user', 'responder']);

        return $this->success([
            'id'             => $feedback->id,
            'type'           => $feedback->type,
            'subject'        => $feedback->subject,
            'body'           => $feedback->body,
            'status'         => $feedback->status,
            'admin_response' => $feedback->admin_response,
            'responded_at'   => $feedback->responded_at?->toIso8601String(),
            'responded_by'   => $feedback->responder ? $feedback->responder->full_name : null,
            'user'           => $feedback->user ? [
                'id'        => $feedback->user->id,
                'full_name' => $feedback->user->full_name,
                'email'     => $feedback->user->email,
            ] : null,
            'created_at' => $feedback->created_at->toIso8601String(),
        ]);
    }

    /**
     * Respond to feedback and update status.
     */
    public function respond(Request $request, Feedback $feedback): JsonResponse
    {
        $validated = $request->validate([
            'status'         => ['required', 'in:reviewed,in_progress,resolved,dismissed'],
            'admin_response' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback->update([
            'status'         => $validated['status'],
            'admin_response' => $validated['admin_response'],
            'responded_by'   => $request->user()->id,
            'responded_at'   => now(),
        ]);

        return $this->success(null, 'Feedback updated.');
    }

    public function destroy(Feedback $feedback): JsonResponse
    {
        $feedback->delete();
        return $this->success(null, 'Feedback deleted.');
    }

    public function pendingCount(): JsonResponse
    {
        return $this->success([
            'pending_count' => Feedback::pending()->count(),
        ]);
    }
}
