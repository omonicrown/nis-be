<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\MemberProfile;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Register a new member.
     * Account starts in 'pending' status until admin approves.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated) {
                // Get default member role
                $memberRole = Role::where('slug', 'member')->first();

                // Create user
                $user = User::create([
                    'first_name'             => $validated['first_name'],
                    'last_name'              => $validated['last_name'],
                    'other_names'            => $validated['other_names'] ?? null,
                    'suffix'                 => $validated['suffix'] ?? null,
                    'email'                  => $validated['email'],
                    'phone'                  => $validated['phone'] ?? null,
                    'gender'                 => $validated['gender'] ?? null,
                    'surcon_reg_no'          => $validated['surcon_reg_no'] ?? null,
                    'nis_membership_id'      => $validated['nis_membership_id'] ?? null,
                    'membership_category_id' => $validated['membership_category_id'],
                    'role_id'                => $memberRole?->id,
                    'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                    'status'                 => UserStatus::PENDING,
                ]);

                // Create profile
                MemberProfile::create([
                    'user_id'             => $user->id,
                    'office_address'      => $validated['office_address'] ?? null,
                    'residential_address' => $validated['residential_address'] ?? null,
                    'date_of_birth'       => $validated['date_of_birth'] ?? null,
                    'specialization'      => $validated['specialization'] ?? null,
                    'firm_name'           => $validated['firm_name'] ?? null,
                ]);

                // Attach subgroups
                if (!empty($validated['subgroup_ids'])) {
                    $user->subgroups()->attach($validated['subgroup_ids']);
                }

                return $user;
            });

            // Fire registered event (triggers email verification notification)
            event(new Registered($user));

            $user->load(['membershipCategory', 'role', 'profile', 'subgroups']);

            return $this->created([
                'user' => new UserResource($user),
            ], 'Registration successful. Your account is pending admin approval.');
        } catch (\Exception $e) {
            return $this->error('Registration failed. Please try again.', 500);
        }
    }

    /**
     * Login and issue Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Find user by NIS Membership ID
        $user = User::where('nis_membership_id', $validated['nis_membership_id'])->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return $this->error('Invalid NIS Membership ID or password.', 401);
        }

        // Delete old tokens (optional: keeps only 1 active session)
        // $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->load(['role', 'membershipCategory', 'profile', 'subgroups']);

        return $this->success([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 'Login successful.');
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Get authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'membershipCategory',
            'role',
            'profile',
            'subgroups',
            'currentExecutivePosition',
        ]);

        return $this->success(new UserResource($user));
    }

    /**
     * Update authenticated user's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name'         => ['sometimes', 'string', 'max:100'],
            'last_name'          => ['sometimes', 'string', 'max:100'],
            'other_names'        => ['nullable', 'string', 'max:100'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'gender'             => ['nullable', 'in:male,female'],

            // Profile fields
            'office_address'           => ['nullable', 'string', 'max:500'],
            'residential_address'      => ['nullable', 'string', 'max:500'],
            'date_of_birth'            => ['nullable', 'date', 'before:today'],
            'bio'                      => ['nullable', 'string', 'max:1000'],
            'specialization'           => ['nullable', 'string', 'max:255'],
            'firm_name'                => ['nullable', 'string', 'max:255'],
            'year_of_registration'     => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],

            // Privacy
            'show_email'               => ['nullable', 'boolean'],
            'show_phone'               => ['nullable', 'boolean'],
            'show_office_address'      => ['nullable', 'boolean'],
            'show_residential_address' => ['nullable', 'boolean'],
            'show_in_directory'        => ['nullable', 'boolean'],

            // Subgroups
            'subgroup_ids'             => ['nullable', 'array'],
            'subgroup_ids.*'           => ['exists:subgroups,id'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            // Update user fields
            $userFields = collect($validated)->only([
                'first_name',
                'last_name',
                'other_names',
                'phone',
                'gender',
            ])->filter()->toArray();

            if (!empty($userFields)) {
                $user->update($userFields);
            }

            // Update or create profile
            $profileFields = collect($validated)->only([
                'office_address',
                'residential_address',
                'date_of_birth',
                'bio',
                'specialization',
                'firm_name',
                'year_of_registration',
                'show_email',
                'show_phone',
                'show_office_address',
                'show_residential_address',
                'show_in_directory',
            ])->toArray();

            if (!empty($profileFields)) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileFields
                );
            }

            // Sync subgroups
            if (isset($validated['subgroup_ids'])) {
                $user->subgroups()->sync($validated['subgroup_ids']);
            }

            // Mark profile as completed if key fields exist
            $profile = $user->profile;
            if ($profile && $user->phone && $profile->office_address) {
                $user->update(['profile_completed' => true]);
            }
        });

        $user->refresh();
        $user->load(['membershipCategory', 'role', 'profile', 'subgroups']);

        return $this->success(new UserResource($user), 'Profile updated successfully.');
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return $this->success(null, 'Password reset link sent to your email.');
        }

        return $this->error('Unable to send reset link. Please try again.');
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password has been reset successfully.');
        }

        return $this->error('Unable to reset password. The token may be invalid or expired.');
    }

    /**
     * Change password (authenticated).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        // Revoke all other tokens
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return $this->success(null, 'Password changed successfully.');
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->error('Invalid verification link.', 400);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }

        $user->markEmailAsVerified();

        return $this->success(null, 'Email verified successfully.');
    }

    /**
     * Resend email verification link.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->success(null, 'Verification email sent.');
    }
}
