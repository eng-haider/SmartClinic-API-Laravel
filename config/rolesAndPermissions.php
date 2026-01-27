<?php

return [
    'roles' => [
        'super_admin' => [
            'display_name' => 'Super Admin',
            'description' => 'Full access to all clinics, patients, cases, and bills',
            'permissions' => [
                // Patient permissions
                'view-all-patients',
                'create-patient',
                'edit-patient',
                'delete-patient',
                'search-patient',

                // Case permissions
                'view-all-cases',
                'create-case',
                'edit-case',
                'delete-case',

                // Bill permissions
                'view-all-bills',
                'create-bill',
                'edit-bill',
                'delete-bill',
                'mark-bill-paid',

                // Clinic permissions
                'view-all-clinics',
                'create-clinic',
                'edit-clinic',
                'delete-clinic',

                // User management
                'view-all-users',
                'create-user',
                'edit-user',
                'delete-user',

                // Reservations
                'view-all-reservations',
                'create-reservation',
                'edit-reservation',
                'delete-reservation',

                // Notes
                'view-notes',
                'create-note',
                'edit-note',
                'delete-note',

                // Recipes
                'view-all-recipes',
                'create-recipe',
                'edit-recipe',
                'delete-recipe',

                // Recipe Items
                'view-recipe-items',
                'create-recipe-item',
                'edit-recipe-item',
                'delete-recipe-item',

                // Clinic Expenses
                'view-clinic-expenses',
                'create-expense',
                'edit-expense',
                'delete-expense',

                // Doctor Management
                'view-doctors',
                'create-doctor',
                'edit-doctor',
                'delete-doctor',

                // Images
                'view-images',
                'create-image',
                'edit-image',
                'delete-image',

                // Reports
                'view-reports',

                // System management
                'manage-permissions',
                'manage-roles',
            ],
        ],

        'clinic_super_doctor' => [
            'display_name' => 'Clinic Super Doctor',
            'description' => 'Manages their clinic - can see all patients, cases, and bills for their clinic',
            'permissions' => [
                // Patient permissions - their clinic
                'view-clinic-patients',
                'create-patient',
                'edit-patient',
                'delete-patient',
                'search-patient',

                // Case permissions - their clinic
                'view-clinic-cases',
                'create-case',
                'edit-case',
                'delete-case',

                // Bill permissions - their clinic
                'view-clinic-bills',
                'create-bill',
                'edit-bill',
                'delete-bill',
                'mark-bill-paid',

                // Clinic permissions
                'view-own-clinic',
                'edit-clinic',

                // User management - their clinic
                'view-clinic-users',
                'create-user',
                'edit-user',

                // Reservations - their clinic
                'view-clinic-reservations',
                'create-reservation',
                'edit-reservation',
                'delete-reservation',

                // Notes
                'view-notes',
                'create-note',
                'edit-note',
                'delete-note',

                // Recipes - their clinic
                'view-all-recipes',
                'create-recipe',
                'edit-recipe',
                'delete-recipe',

                // Recipe Items
                'view-recipe-items',
                'create-recipe-item',
                'edit-recipe-item',
                'delete-recipe-item',

                // Clinic Expenses
                'view-clinic-expenses',
                'create-expense',
                'edit-expense',
                'delete-expense',

                // Doctor Management
                'view-doctors',
                'create-doctor',
                'edit-doctor',
                'delete-doctor',

                // Images
                'view-images',
                'create-image',
                'edit-image',
                'delete-image',

                // Reports
                'view-reports',

                // Secretary Management
                'delete-user',
            ],
        ],

        'doctor' => [
            'display_name' => 'Doctor',
            'description' => 'Can see clinic patients but only their own cases and bills',
            'permissions' => [
                // Patient permissions - their clinic
                'view-clinic-patients',
                'create-patient',
                'edit-patient',
                'search-patient',

                // Case permissions - only their own
                'view-own-cases',
                'create-case',
                'edit-case',

                // Bill permissions - only their own
                'view-own-bills',
                'create-bill',
                'edit-bill',
                'mark-bill-paid',

                // Clinic permissions
                'view-own-clinic',

                // User management - view only
                'view-clinic-users',

                // Reservations - their own
                'view-own-reservations',
                'create-reservation',
                'edit-reservation',

                // Notes
                'view-notes',
                'create-note',
                'edit-note',

                // Recipes - only their own
                'view-own-recipes',
                'create-recipe',
                'edit-recipe',
                'delete-recipe',

                // Recipe Items
                'view-recipe-items',
                'create-recipe-item',
                'edit-recipe-item',
                'delete-recipe-item',

                // Clinic Expenses
                'view-clinic-expenses',

                // Doctor Management - view only
                'view-doctors',

                // Images
                'view-images',
                'create-image',
                'edit-image',
                'delete-image',

                // Reports
                'view-reports',
            ],
        ],

        'secretary' => [
            'display_name' => 'Secretary',
            'description' => 'Can manage patients and reservations, view basic information',
            'permissions' => [
                // Patient permissions
                'view-clinic-patients',
                'create-patient',
                'edit-patient',
                'search-patient',

                // Case permissions - view only
                'view-clinic-cases',

                // Bill permissions - view and create
                'view-clinic-bills',
                'create-bill',
                'mark-bill-paid',

                // Clinic permissions
                'view-own-clinic',

                // Reservations
                'view-clinic-reservations',
                'create-reservation',
                'edit-reservation',
                'delete-reservation',

                // Notes
                'view-notes',
                'create-note',

                // Clinic Expenses - view only
                'view-clinic-expenses',

                // Doctor Management - view only
                'view-doctors',

                // Images - view only
                'view-images',

                // Reports
                'view-reports',
            ],
        ],
    ],
];
