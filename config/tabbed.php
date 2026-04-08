<?php

// config for JibayMcs/Tabbed
return [

    /*
    |--------------------------------------------------------------------------
    | Page par défaut
    |--------------------------------------------------------------------------
    |
    | La page Filament ouverte par défaut lors de l'ouverture d'un onglet.
    | Valeurs possibles : 'edit', 'view', 'create', 'index'
    |
    */
    'default_page' => 'edit',

    /*
    |--------------------------------------------------------------------------
    | Clé localStorage
    |--------------------------------------------------------------------------
    |
    | Clé utilisée pour persister les onglets dans le localStorage du navigateur.
    | Utile pour le support multi-panel (une clé différente par panel).
    |
    */
    'persist_key' => 'tabbed_tabs',

];
