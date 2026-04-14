<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AttendanceUploadController;
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\ExecutiveController;
use App\Http\Controllers\Api\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\Admin\ForumController as AdminForumController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\MeetingController as AdminMeetingController;
use App\Http\Controllers\Api\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ResourceController as AdminResourceController;
use App\Http\Controllers\Api\Admin\RolePermissionController;
use App\Http\Controllers\Api\Member\ContentController;
use App\Http\Controllers\Api\Member\DirectoryController;
use App\Http\Controllers\Api\Member\EventController as MemberEventController;
use App\Http\Controllers\Api\Member\FeedbackController as MemberFeedbackController;
use App\Http\Controllers\Api\Member\ForumController as MemberForumController;
use App\Http\Controllers\Api\Member\MeetingController as MemberMeetingController;
use App\Http\Controllers\Api\Member\MessageController;
use App\Http\Controllers\Api\Member\PaymentController as MemberPaymentController;
use App\Http\Controllers\Api\Member\ProfileController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\PublicContentController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — NIS Oyo State Branch (Complete)
|--------------------------------------------------------------------------
*/

// ─── Public Routes (No Auth Required) ───────────────────────────────

Route::prefix('public')->group(function () {
    Route::get('/membership-categories', [PublicController::class, 'membershipCategories']);
    Route::get('/subgroups', [PublicController::class, 'subgroups']);
    Route::get('/executives', [PublicController::class, 'executives']);
    Route::get('/search-surveyor', [PublicController::class, 'searchSurveyor']);

    // Public content (Phase 5)
    Route::get('/posts', [PublicContentController::class, 'posts']);
    Route::get('/posts/{slug}', [PublicContentController::class, 'showPost']);
    Route::get('/events', [PublicContentController::class, 'events']);
    Route::get('/events/{slug}', [PublicContentController::class, 'showEvent']);
    Route::get('/announcements', [PublicContentController::class, 'announcements']);
    Route::get('/resources', [PublicContentController::class, 'resources']);

    Route::get('/reports/attendance/monthly/export', [AttendanceUploadController::class, 'exportMonthlyReport']);
});

// ─── Auth Routes ────────────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    });
});

// ─── Paystack Webhook ───────────────────────────────────────────────

Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle']);

// ─── Authenticated Member Routes (Must be active) ───────────────────

Route::middleware(['auth:sanctum', 'approved'])->prefix('member')->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        $user = request()->user();
        $user->load(['membershipCategory', 'profile', 'subgroups', 'currentExecutivePosition']);

        // Safe status label
        $statusLabel = $user->status instanceof \App\Enums\UserStatus
            ? $user->status->label()
            : ucfirst($user->status ?? '');

        // Safe counts — tables might not exist yet
        try {
            $upcomingMeetings = \App\Models\Meeting::upcoming()->count();
        } catch (\Exception $e) {
            $upcomingMeetings = 0;
        }
        try {
            $pendingPayments = \App\Models\Payment::where('user_id', $user->id)->where('status', 'pending')->count();
        } catch (\Exception $e) {
            $pendingPayments = 0;
        }
        try {
            $unreadAnnouncements = \App\Models\Announcement::active()->forMembers()->count();
        } catch (\Exception $e) {
            $unreadAnnouncements = 0;
        }
        try {
            $unreadMessages = \App\Models\Message::unread($user->id)->count();
        } catch (\Exception $e) {
            $unreadMessages = 0;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user'                 => new \App\Http\Resources\UserResource($user),
                'membership_status'    => $statusLabel,
                'membership_category'  => $user->membershipCategory?->name,
                'designation'          => $user->designation,
                'profile_completed'    => (bool) $user->profile_completed,
                'upcoming_meetings'    => $upcomingMeetings,
                'pending_payments'     => $pendingPayments,
                'unread_announcements' => $unreadAnnouncements,
                'unread_messages'      => $unreadMessages,
            ],
        ]);
    });

    // Profile (Phase 2)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);
    Route::get('/profile/completion', [ProfileController::class, 'completionStatus']);

    // Directory (Phase 2)
    Route::get('/directory', [DirectoryController::class, 'index']);
    Route::get('/directory/filters', [DirectoryController::class, 'filters']);
    Route::get('/directory/{user}', [DirectoryController::class, 'show']);

    // Meetings & Attendance (Phase 3)
    Route::get('/meetings', [MemberMeetingController::class, 'index']);
    Route::get('/meetings/{meeting}', [MemberMeetingController::class, 'show']);
    Route::post('/meetings/qr-checkin', [MemberMeetingController::class, 'qrCheckIn']);
    Route::post('/meetings/{meeting}/apology', [MemberMeetingController::class, 'submitApology']);
    Route::get('/attendance/history', [MemberMeetingController::class, 'myAttendance']);

    // Payments (Phase 4)
    Route::post('/payments/initialize', [MemberPaymentController::class, 'initialize']);
    Route::post('/payments/verify', [MemberPaymentController::class, 'verify']);
    Route::get('/payments', [MemberPaymentController::class, 'history']);
    Route::get('/payments/dues-status', [MemberPaymentController::class, 'duesStatus']);
    Route::get('/payments/{payment}', [MemberPaymentController::class, 'show']);
    Route::post('/payments/{payment}/proof', [MemberPaymentController::class, 'uploadProof']);

    // Content & Blog (Phase 5)
    Route::get('/posts', [ContentController::class, 'posts']);
    Route::get('/posts/{slug}', [ContentController::class, 'showPost']);
    Route::get('/announcements', [ContentController::class, 'announcements']);
    Route::get('/resources', [ContentController::class, 'resources']);
    Route::get('/resources/categories', [ContentController::class, 'resourceCategories']);
    Route::get('/resources/{resource}/download', [ContentController::class, 'downloadResource']);

    // Events (Phase 5)
    Route::get('/events', [MemberEventController::class, 'index']);
    Route::get('/events/{event}', [MemberEventController::class, 'show']);
    Route::post('/events/{event}/register', [MemberEventController::class, 'register']);
    Route::post('/events/{event}/cancel', [MemberEventController::class, 'cancelRegistration']);
    Route::get('/my-events', [MemberEventController::class, 'myEvents']);

    // ─── Forum (Phase 6) ────────────────────────────────────────
    Route::get('/forum/categories', [MemberForumController::class, 'categories']);
    Route::get('/forum/categories/{forumCategory}/topics', [MemberForumController::class, 'topics']);
    Route::get('/forum/topics/{forumTopic}', [MemberForumController::class, 'showTopic']);
    Route::post('/forum/topics', [MemberForumController::class, 'createTopic']);
    Route::put('/forum/topics/{forumTopic}', [MemberForumController::class, 'updateTopic']);
    Route::delete('/forum/topics/{forumTopic}', [MemberForumController::class, 'deleteTopic']);
    Route::post('/forum/topics/{forumTopic}/replies', [MemberForumController::class, 'reply']);
    Route::put('/forum/replies/{forumReply}', [MemberForumController::class, 'updateReply']);
    Route::delete('/forum/replies/{forumReply}', [MemberForumController::class, 'deleteReply']);

    // ─── Messages (Phase 6) ────────────────────────────────────
    Route::get('/messages/inbox', [MessageController::class, 'inbox']);
    Route::get('/messages/sent', [MessageController::class, 'sent']);
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
    Route::get('/messages/{message}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'send']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);

    // ─── Feedback (Phase 6) ────────────────────────────────────
    Route::get('/feedback', [MemberFeedbackController::class, 'index']);
    Route::post('/feedback', [MemberFeedbackController::class, 'store']);
});

// ─── Admin Routes ───────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'approved', 'role:super_admin,admin'])->prefix('admin')->group(function () {

    // Members
    Route::get('/members', [AdminMemberController::class, 'index']);
    Route::get('/members/pending-count', [AdminMemberController::class, 'pendingCount']);
    Route::get('/members/{user}', [AdminMemberController::class, 'show']);
    Route::post('/members/{user}/approve', [AdminMemberController::class, 'approve']);
    Route::post('/members/{user}/reject', [AdminMemberController::class, 'reject']);
    Route::post('/members/{user}/suspend', [AdminMemberController::class, 'suspend']);
    Route::post('/members/{user}/reactivate', [AdminMemberController::class, 'reactivate']);
    Route::put('/members/{user}/role', [AdminMemberController::class, 'updateRole']);
    Route::put('/members/{user}/category', [AdminMemberController::class, 'updateCategory']);
    Route::post('/members/bulk-approve', [AdminMemberController::class, 'bulkApprove']);
    Route::put('/members/{user}/profile', [AdminMemberController::class, 'updateProfile']);


    // Inside the admin group:
    Route::get('/executives', [ExecutiveController::class, 'index']);
    Route::post('/executives', [ExecutiveController::class, 'store']);
    Route::get('/executives/{executive}', [ExecutiveController::class, 'show']);
    Route::put('/executives/{executive}', [ExecutiveController::class, 'update']);
    Route::post('/executives/{executive}/photo', [ExecutiveController::class, 'uploadPhoto']);
    Route::delete('/executives/{executive}', [ExecutiveController::class, 'destroy']);
    Route::post('/executives/reorder', [ExecutiveController::class, 'reorder']);


    Route::post('/meetings/{meeting}/attendance/upload', [AttendanceUploadController::class, 'upload']);
    Route::get('/meetings/{meeting}/attendance/template', [AttendanceUploadController::class, 'downloadTemplate']);
    Route::get('/reports/attendance/monthly', [AttendanceUploadController::class, 'monthlyReport']);
    Route::get('/reports/attendance/monthly/export', [AttendanceUploadController::class, 'exportMonthlyReport']);
    Route::get('/members/{user}/attendance', [AttendanceUploadController::class, 'memberAttendance']);

    // Meetings (Phase 3)
    Route::get('/meetings', [AdminMeetingController::class, 'index']);
    Route::post('/meetings', [AdminMeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [AdminMeetingController::class, 'show']);
    Route::put('/meetings/{meeting}', [AdminMeetingController::class, 'update']);
    Route::delete('/meetings/{meeting}', [AdminMeetingController::class, 'destroy']);
    Route::patch('/meetings/{meeting}/status', [AdminMeetingController::class, 'updateStatus']);
    Route::post('/meetings/{meeting}/qr-regenerate', [AdminMeetingController::class, 'regenerateQrCode']);
    Route::post('/meetings/{meeting}/minutes', [AdminMeetingController::class, 'uploadMinutes']);
    Route::put('/meetings/{meeting}/minutes-text', [AdminMeetingController::class, 'updateMinutesText']);
    Route::delete('/meetings/{meeting}/minutes', [AdminMeetingController::class, 'deleteMinutes']);
    Route::get('/meetings/{meeting}/attendance', [AdminMeetingController::class, 'attendance']);
    Route::post('/meetings/{meeting}/attendance', [AdminMeetingController::class, 'markAttendance']);
    Route::post('/meetings/{meeting}/attendance/bulk', [AdminMeetingController::class, 'bulkMarkAttendance']);
    Route::post('/meetings/{meeting}/attendance/initialize', [AdminMeetingController::class, 'initializeAttendance']);

    // Payments (Phase 4)
    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::get('/payments/stats', [AdminPaymentController::class, 'stats']);
    Route::get('/payments/pending-manual', [AdminPaymentController::class, 'pendingManualCount']);
    Route::get('/payments/outstanding-dues', [AdminPaymentController::class, 'outstandingDues']);
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
    Route::post('/payments/{payment}/verify', [AdminPaymentController::class, 'verifyManual']);
    Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'rejectManual']);
    Route::post('/payments/record', [AdminPaymentController::class, 'recordPayment']);
    Route::post('/payments/generate-reminders', [AdminPaymentController::class, 'generateReminders']);

    // Blog / Posts (Phase 5)
    Route::get('/posts', [AdminPostController::class, 'index']);
    Route::post('/posts', [AdminPostController::class, 'store']);
    Route::get('/posts/{post}', [AdminPostController::class, 'show']);
    Route::put('/posts/{post}', [AdminPostController::class, 'update']);
    Route::delete('/posts/{post}', [AdminPostController::class, 'destroy']);
    Route::post('/posts/{post}/image', [AdminPostController::class, 'uploadImage']);

    // Events (Phase 5)
    Route::get('/events', [AdminEventController::class, 'index']);
    Route::post('/events', [AdminEventController::class, 'store']);
    Route::get('/events/{event}', [AdminEventController::class, 'show']);
    Route::put('/events/{event}', [AdminEventController::class, 'update']);
    Route::delete('/events/{event}', [AdminEventController::class, 'destroy']);
    Route::post('/events/{event}/banner', [AdminEventController::class, 'uploadBanner']);
    Route::patch('/events/{event}/status', [AdminEventController::class, 'updateStatus']);
    Route::get('/events/{event}/registrations', [AdminEventController::class, 'registrations']);

    // Resources (Phase 5)
    Route::get('/resources', [AdminResourceController::class, 'index']);
    Route::post('/resources', [AdminResourceController::class, 'store']);
    Route::get('/resources/{resource}', [AdminResourceController::class, 'show']);
    Route::put('/resources/{resource}', [AdminResourceController::class, 'update']);
    Route::post('/resources/{resource}/file', [AdminResourceController::class, 'replaceFile']);
    Route::delete('/resources/{resource}', [AdminResourceController::class, 'destroy']);

    // Announcements (Phase 5)
    Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
    Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
    Route::get('/announcements/{announcement}', [AdminAnnouncementController::class, 'show']);
    Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);
    Route::post('/announcements/{announcement}/toggle', [AdminAnnouncementController::class, 'toggleActive']);

    // ─── Forum Admin (Phase 6) ──────────────────────────────────
    Route::get('/forum/categories', [AdminForumController::class, 'categories']);
    Route::post('/forum/categories', [AdminForumController::class, 'storeCategory']);
    Route::put('/forum/categories/{forumCategory}', [AdminForumController::class, 'updateCategory']);
    Route::delete('/forum/categories/{forumCategory}', [AdminForumController::class, 'deleteCategory']);
    Route::post('/forum/topics/{forumTopic}/pin', [AdminForumController::class, 'pinTopic']);
    Route::post('/forum/topics/{forumTopic}/lock', [AdminForumController::class, 'lockTopic']);
    Route::delete('/forum/topics/{forumTopic}', [AdminForumController::class, 'deleteTopic']);
    Route::delete('/forum/replies/{forumReply}', [AdminForumController::class, 'deleteReply']);
    Route::get('/forum/stats', [AdminForumController::class, 'stats']);

    // ─── Feedback Admin (Phase 6) ───────────────────────────────
    Route::get('/feedback', [AdminFeedbackController::class, 'index']);
    Route::get('/feedback/pending-count', [AdminFeedbackController::class, 'pendingCount']);
    Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show']);
    Route::post('/feedback/{feedback}/respond', [AdminFeedbackController::class, 'respond']);
    Route::delete('/feedback/{feedback}', [AdminFeedbackController::class, 'destroy']);

    // ─── Reports & Analytics (Phase 6) ──────────────────────────
    Route::get('/reports/overview', [ReportController::class, 'overview']);
    Route::get('/reports/membership', [ReportController::class, 'membershipReport']);
    Route::get('/reports/attendance', [ReportController::class, 'attendanceReport']);
    Route::get('/reports/financial', [ReportController::class, 'financialReport']);
    Route::get('/reports/engagement', [ReportController::class, 'engagementReport']);

    // Dashboard stats
    Route::get('/dashboard', function () {
        return response()->json([
            'success' => true,
            'data'    => [
                'total_members'           => \App\Models\User::active()->count(),
                'pending_approvals'       => \App\Models\User::pending()->count(),
                'total_fellows'           => \App\Models\User::byCategory('fellow')->active()->count(),
                'total_members_cat'       => \App\Models\User::byCategory('member')->active()->count(),
                'upcoming_meetings'       => \App\Models\Meeting::upcoming()->count(),
                'pending_manual_payments' => \App\Models\Payment::manualPending()->count(),
                'revenue_this_year'       => \App\Models\Payment::completed()->byYear(date('Y'))->sum('amount'),
                'upcoming_events'         => \App\Models\Event::upcoming()->count(),
                'active_announcements'    => \App\Models\Announcement::active()->count(),
                'pending_feedback'        => \App\Models\Feedback::pending()->count(),
                'forum_topics'            => \App\Models\ForumTopic::count(),
            ],
        ]);
    });

    // Import & Export
    Route::get('/import/existing', [ImportController::class, 'importExisting']);
    Route::get('/import/new', [ImportController::class, 'importNew']);
    Route::get('/export/members', [ImportController::class, 'exportMembers']);
});

Route::middleware('role:super_admin')->group(function () {
    Route::get('/roles', [RolePermissionController::class, 'roles']);
    Route::post('/roles', [RolePermissionController::class, 'createRole']);
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'updateRolePermissions']);
    Route::get('/permissions', [RolePermissionController::class, 'permissions']);
    Route::get('/admins', [RolePermissionController::class, 'admins']);
    Route::post('/users/{user}/assign-role', [RolePermissionController::class, 'assignRole']);
    Route::get('/users/{user}/permissions', [RolePermissionController::class, 'userPermissions']);
    Route::post('/permissions', [RolePermissionController::class, 'createPermission']);
    Route::delete('/permissions/{permission}', [RolePermissionController::class, 'deletePermission']);
});

// ─── Cron Routes ────────────────────────────────────────────────────

Route::middleware('cron')->prefix('cron')->group(function () {
    Route::get('/import/existing', [ImportController::class, 'importExisting']);
    Route::get('/import/new', [ImportController::class, 'importNew']);
    Route::get('/export/members', [ImportController::class, 'exportMembers']);
    Route::get('/payments/generate-reminders', [AdminPaymentController::class, 'generateReminders']);
});
