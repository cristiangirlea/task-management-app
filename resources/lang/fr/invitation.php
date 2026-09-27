<?php

return [
    'store' => [
        'success' => 'Invitation envoyée.',
        'mail_failed' => 'Invitation créée, mais l\'e-mail n\'a pas pu être envoyé. Partagez plutôt le lien ci-dessous.',
    ],
    'destroy' => [
        'success' => 'Invitation révoquée.',
    ],
    'resend' => [
        'success' => 'Invitation renvoyée avec un nouveau lien.',
        'mail_failed' => 'Nouveau lien créé, mais l\'e-mail n\'a pas pu être envoyé. Partagez plutôt le lien ci-dessous.',
        'unavailable' => 'Seules les invitations en attente peuvent être renvoyées. Invitez plutôt la personne à nouveau.',
        'registered' => 'Cette adresse e-mail a désormais un compte : l\'invitation ne peut plus être acceptée. Révoquez-la.',
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
