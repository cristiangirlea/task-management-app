<?php

return [
    'store' => [
        'success' => 'Invitation sent.',
        'mail_failed' => 'Invitation created, but the email could not be sent. Share the link below instead.',
    ],
    'destroy' => [
        'success' => 'Invitation revoked.',
    ],
    'resend' => [
        'success' => 'Invitation sent again with a new link.',
        'mail_failed' => 'New link created, but the email could not be sent. Share the link below instead.',
        'unavailable' => 'Only pending invitations can be sent again. Invite the person anew instead.',
        'registered' => 'This email now has an account, so the invitation can no longer be accepted. Revoke it.',
    ],
    'accept' => [
        'success' => 'Welcome to :workspace.',
        'already_registered' => 'This email already has an account. Sign in instead.',
        'unavailable' => 'This invitation has expired or was already used.',
    ],
    'validation' => [
        'email_taken' => 'This email already has an account or a pending invitation.',
    ],
    'mail' => [
        'subject' => 'You have been invited to :workspace',
        'heading' => 'Join :workspace',
        'invited_by' => ':name has invited you to join the :workspace workspace.',
        'invited' => 'You have been invited to join the :workspace workspace.',
        'button' => 'Accept invitation',
        'expires' => 'This invitation expires on :date.',
        'ignore' => 'If you were not expecting this, you can ignore this email.',
    ],
];
