<?php

return [
    // Default SLA (days) per official priority.
    'sla_days' => ['Critical' => 1, 'High' => 3, 'Medium' => 7, 'Low' => 14],

    'photos' => ['min' => 1, 'max' => 3, 'max_kb' => 2048],

    'pdf_max_rows' => 500,

    // Manual search keywords per category, for items that are not in the BMN asset list (building parts).
    'category_keywords' => [
        'Bangunan & Sarana' => [
            'pintu', 'jendela', 'plafon', 'atap', 'dinding', 'lantai', 'toilet', 'wc', 'keran', 'wastafel',
            'pipa', 'saluran', 'stop kontak', 'saklar', 'listrik', 'lampu ruangan', 'kunci pintu', 'tangga', 'cat',
        ],
    ],

    // NIM/NIP of the seeded super admin; this account can never be deleted.
    'super_admin_nip' => env('SUPER_ADMIN_NIP', 'admin'),
];
