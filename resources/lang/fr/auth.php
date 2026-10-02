<?php

return [
    'login' => [
        'success' => 'Connecté avec succès.',
        'error' => 'Identifiants invalides. Veuillez réessayer.',
    ],
    'register' => [
        'success' => 'Inscription réussie.',
    ],
    'logout' => [
        'success' => 'Vous vous êtes déconnecté avec succès.',
    ],
    'two_factor' => [
        'challenge' => "Saisissez le code de votre application d'authentification.",
        'started' => "Scannez le code QR avec votre application d'authentification, puis saisissez le code affiché.",
        'enabled' => "L'authentification à deux facteurs est activée. Conservez vos codes de récupération en lieu sûr.",
        'disabled' => "L'authentification à deux facteurs est désactivée.",
        'recovery_codes' => 'Nouveaux codes de récupération créés. Les anciens ne fonctionnent plus.',
        'already_enabled' => "L'authentification à deux facteurs est déjà activée.",
        'not_enabled' => "L'authentification à deux facteurs n'est pas activée.",
        'not_started' => "Commencez d'abord la configuration de l'authentification à deux facteurs.",
        'invalid_code' => "Ce code n'est pas valide.",
        'challenge_expired' => 'Cette connexion a expiré. Reconnectez-vous.',
        'too_many_attempts' => 'Trop de codes erronés. Reconnectez-vous.',
    ],
];
