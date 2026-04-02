<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    use ApiResponse;

    /**
     * Submit feedback.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'in:suggestion,issue,testimonial,complaint,other'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:5000'],
        ]);

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'type'    => $validated['type'],
            'subject' => $validated['subject'],
            'body'    => $validated['body'],
            'status'  => 'pending',
        ]);

        return $this->created([
            'id'     => $feedback->id,
            'status' => $feedback->status,
        ], 'Feedback submitted. Thank you!');
    }

    /**
     * My feedback history.
     */
    public function index(Request $request): JsonResponse
    {
        $feedbacks = Feedback::where('user_id', $request->user()->id)
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        $transformed = $feedbacks->through(fn($f) => [
            'id'             => $f->id,
            'type'           => $f->type,
            'subject'        => $f->subject,
            'body'           => $f->body,
            'status'         => $f->status,
            'admin_response' => $f->admin_response,
            'responded_at'   => $f->responded_at?->toIso8601String(),
            'created_at'     => $f->created_at->toIso8601String(),
        ]);

        return $this->paginated($transformed);
    }
}
