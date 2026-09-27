<?php

return [
    'store' => [
        'success' => 'Invitation envoyée.',
    ],
    'destroy' => [
        'success' => 'Invitation révoquée.',
    ],
    'resend' => [
        'success' => 'Invitation renvoyée avec un nouveau lien.',
        'unavailable' => 'Seules les invitations en attente peuvent être renvoyées. Invitez plutôt la personne à nouveau.',
    ],
    'accept' => [
        'success' => 'Bienvenue dans :workspace.',
        'already_registered' => 'Cette adresse e-mail a déjà un compte. Connectez-vous plutôt.',
        'unavailable' => 'Cette invitation a expiré ou a déjà été utilisée.',
    ],
    'validation' => [
        'email_taken' => 'Cette adresse e-mail a déjà un compte ou une invitation en attente.',
    ],
    'mail' => [
        'subject' => 'Vous êtes invité à rejoindre :workspace',
        'heading' => 'Rejoindre :workspace',
        'invited_by' => ':name vous invite à rejoindre l\'espace de travail :workspace.',
        'invited' => 'Vous êtes invité à rejoindre l\'espace de travail :workspace.',
        'button' => 'Accepter l\'invitation',
        'expires' => 'Cette invitation expire le :date.',
        'ignore' => 'Si vous n\'attendiez pas ce message, vous pouvez l\'ignorer.',
    ],
];
