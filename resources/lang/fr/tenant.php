<?php

return [
    'store' => [
        'validation' => [
            'required_fields' => 'Tous les champs nécessaires doivent être fournis pour créer un locataire.',
            'unique_error' => 'Le nom du locataire doit être unique.',
            'invalid_format' => 'Un ou plusieurs champs ont un format invalide.',
        ],
        'success' => 'Le locataire a été créé avec succès.',
        'error' => 'Échec de la création du locataire.',
    ],
    'update' => [
        'validation' => [
            'required_fields' => 'Tous les champs nécessaires doivent être fournis pour mettre à jour le locataire.',
            'unique_error' => 'Le nom mis à jour du locataire doit être unique.',
            'missing_id' => 'L\'ID du locataire est requis pour effectuer la mise à jour.',
        ],
        'success' => 'Le locataire a été mis à jour avec succès.',
        'error' => 'Échec de la mise à jour du locataire.',
    ],
    'destroy' => [
        'validation' => [
            'missing_id' => 'L\'ID du locataire doit être fourni pour pouvoir le supprimer.',
        ],
        'success' => 'Le locataire a été supprimé avec succès.',
        'error' => 'Échec de la suppression du locataire.',
    ],
    'not_found' => [
        'single' => 'Locataire non trouvé.',
        'multiple' => 'Aucun locataire trouvé.',
    ],
];
