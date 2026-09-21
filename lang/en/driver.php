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
        'status' => 'Status',
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
