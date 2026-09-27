<?php

return [
    // Laravel's broker status keys.
    'reset' => 'Your password has been reset.',
    'sent' => 'If an account exists for that address, we have sent a password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset link is invalid or has expired.',
    'user' => 'If an account exists for that address, we have sent a password reset link.',

    'mail' => [
        'subject' => 'Reset your password',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'button' => 'Reset password',
        'expires' => 'This link will expire in :count minutes.',
        'ignore' => 'If you did not request a password reset, no further action is required.',
    ],
];
