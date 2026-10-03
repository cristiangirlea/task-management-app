<?php

return [
    'login' => [
        'success' => 'Logged in successfully.',
        'error' => 'Invalid credentials. Please try again.',
    ],
    'register' => [
        'success' => 'Registered successfully.',
    ],
    'logout' => [
        'success' => 'You have successfully logged out.',
    ],
    'two_factor' => [
        'challenge' => 'Enter the code from your authenticator app.',
        'started' => 'Scan the QR code with your authenticator app, then enter the code it shows.',
        'enabled' => 'Two-factor authentication is on. Keep your recovery codes somewhere safe.',
        'disabled' => 'Two-factor authentication is off.',
        'recovery_codes' => 'New recovery codes created. The old ones no longer work.',
        'already_enabled' => 'Two-factor authentication is already on.',
        'not_enabled' => 'Two-factor authentication is not on.',
        'not_started' => 'Start setting up two-factor authentication first.',
        'invalid_code' => 'That code is not valid.',
        'challenge_expired' => 'This sign-in has expired. Sign in again.',
        'too_many_attempts' => 'Too many wrong codes. Sign in again.',
    ],
    'password_reset' => [
        'sent' => 'Password reset link has been sent!',
        'success' => 'Your password has been reset successfully.',
        'error' => 'Failed to reset password.',
    ],
];
