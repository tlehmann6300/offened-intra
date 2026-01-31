<?php
declare(strict_types=1);

/**
 * Inventory Configuration
 * Predefined locations and inventory-specific settings
 * 
 * Note: This file should be included from files that have already loaded config.php
 * where BASE_PATH and other required constants are defined.
 * 
 * This file returns an array with configuration values.
 */

return [
    // Predefined locations for inventory items
    // These can be used as dropdown options in the inventory management interface
    'locations' => [
        'Büro',
        'Lager',
        'Vorstand',
        'Konferenzraum',
        'Empfang',
        'Küche',
        'IT-Raum',
        'Meetingraum A',
        'Meetingraum B',
        'Werkstatt',
        'Archiv',
        'Außenlager',
        'Keller'
    ],
    
    // Inventory categories
    // Used for validation and dropdown options in the inventory system
    'categories' => [
        'it' => 'IT',
        'event' => 'Event',
        'moebel' => 'Möbel',
        'technik' => 'Technik',
        'marketing' => 'Marketing',
        'buero' => 'Büro',
        'veranstaltung' => 'Veranstaltung',
        'sonstiges' => 'Sonstiges'
    ]
];
