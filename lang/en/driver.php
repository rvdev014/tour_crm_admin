<?php

return [
    'app_name' => 'Driver cabinet',

    // Driver-side progress of a transfer. Labels are shown in the admin list and in the driver cabinet.
    'statuses' => [
        'assigned' => 'Assigned',
        'en_route_to_client' => 'On the way to client',
        'waiting_for_client' => 'Waiting for client',
        'on_the_way' => 'On the trip',
        'completed' => 'Completed',
    ],

    // Text of the button that moves a transfer INTO the given status.
    'actions' => [
        'en_route_to_client' => 'I\'m on my way',
        'waiting_for_client' => 'I\'ve arrived',
        'on_the_way' => 'Start trip',
        'completed' => 'Finish trip',
    ],

    'auth' => [
        'title' => 'Driver sign-in',
        'phone' => 'Phone',
        'password' => 'Password',
        'submit' => 'Sign in',
        'failed' => 'Wrong phone number or password.',
        'logout' => 'Sign out',
        'hint' => 'Your login and password are issued by the operator.',
    ],

    'nav' => [
        'back' => 'Back',
        'today' => 'Today',
    ],

    'list' => [
        'empty' => 'No transfers for this day',
        'passengers' => ':count pax',
    ],

    'show' => [
        'title' => 'Transfer #:number',
        'date' => 'Date and time',
        'destination' => 'Destination',
        'pickup' => 'Pickup point',
        'terminal' => 'Terminal / details',
        'city' => 'City',
        'pax' => 'Passengers',
        'passenger' => 'Passenger',
        'nameplate' => 'Nameplate',
        'mark' => 'Vehicle',
        'transport' => 'Class',
        'comment' => 'Note',
        'open_map' => 'Open on map',
        'client' => 'Client',
        'call_client' => 'Call',
        'status' => 'Status',
    ],

    // Dispatcher accounts: see every transfer and every driver, may set any status.
    'dispatcher' => [
        'tab_transfers' => 'Transfers',
        'tab_drivers' => 'Drivers',
        'driver' => 'Driver',
        'no_driver' => 'No driver',
        'call' => 'Call',
        'save' => 'Save',
        'no_drivers' => 'No drivers yet',
        'trips_today' => 'Today: :count',
        'no_trips' => 'Free today',
        'disabled' => 'Disabled',
    ],

    // Money the driver spent on a trip (amounts are always in sums).
    'expenses' => [
        'title' => 'Expenses',
        'add' => 'Add expense',
        'empty' => 'No expenses yet',
        'total' => 'Total: :amount',
        'type' => 'Expense type',
        'type_placeholder' => 'Choose a type',
        'amount' => 'Amount',
        'receipt' => 'Receipt photo',
        'receipt_hint' => 'JPG, PNG or WebP, up to 10 MB',
        'photo' => 'Photo',
        'delete' => 'Delete',
        'delete_title' => 'Delete this expense?',
        'delete_text' => 'The receipt photo will be deleted too.',
        'delete_yes' => 'Yes, delete',
        'added' => 'Expense added and sent for review.',
        'deleted' => 'Expense deleted.',
        'cannot_delete' => 'This expense can no longer be deleted.',
        'added_by' => 'Added by :name',
        'rejected_note' => 'Reason for rejection: :note',
        'types' => [
            'driver_services' => 'Driver services',
            'parking' => 'Parking',
            'fuel' => 'Fuel',
            'road' => 'Road / tolls',
            'wash' => 'Car wash',
            'water' => 'Water',
            'other' => 'Other',
        ],
        'statuses' => [
            'new' => 'Under review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ],
        'errors' => [
            'type' => 'Choose an expense type.',
            'amount' => 'Enter the amount as a whole number of sums, e.g. 150 000.',
            'receipt_missing' => 'Add a photo of the receipt. If you picked one, it may be too large.',
            'receipt_format' => 'The photo must be a JPG, PNG or WebP image.',
            'receipt_size' => 'The photo is too large (10 MB at most).',
        ],
    ],

    'flow' => [
        'closed' => 'This transfer was closed by the operator — its status can no longer be changed.',
        'stale' => 'The status has already changed. The page was refreshed.',
        'updated' => 'Status: :status',
        'finished' => 'Trip finished',
        'confirm_title' => 'Finish the trip?',
        'confirm_text' => 'Once finished, you cannot change the status yourself.',
        'confirm_yes' => 'Yes, finish',
        'confirm_no' => 'Cancel',
    ],
];
