<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends ApiBaseController
{
    /**
     * Verify from the emailed link. The route is signed rather than
     * authenticated so the link works in any mail client, on any device.
     * Afterwards the browser is sent back to the SPA with a status.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->backToApp('invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->backToApp('already-verified');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->backToApp('success');
    }

    /**
     * Resend the verification email to the signed-in user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->respondApiSuccess(null, null, __('verification.already_verified'));
        }

        $user->sendEmailVerificationNotification();

        return $this->respondApiSuccess(null, null, __('verification.sent'), 202);
    }

    private function backToApp(string $status): RedirectResponse
    {
        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/').'/verify-email?status='.$status
        );
    }
}
