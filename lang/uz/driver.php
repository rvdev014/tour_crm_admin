<?php

// NOTE: Uzbek (Latin) strings — please have a native speaker review before rollout.
// Any key missing here falls back to Russian (config/app.php fallback_locale), never to a raw key.
return [
    'app_name' => 'Haydovchi kabineti',

    // Driver-side progress of a transfer. Labels are shown in the admin list and in the driver cabinet.
    'statuses' => [
        'assigned' => 'Tayinlangan',
        'en_route_to_client' => 'Mijoz tomon yo\'ldaman',
        'waiting_for_client' => 'Mijozni kutyapman',
        'on_the_way' => 'Yo\'lda',
        'completed' => 'Yakunlangan',
    ],

    // Text of the button that moves a transfer INTO the given status.
    'actions' => [
        'en_route_to_client' => 'Yo\'lga chiqdim',
        'waiting_for_client' => 'Yetib keldim',
        'on_the_way' => 'Safarni boshladim',
        'completed' => 'Yakunladim',
    ],

    'auth' => [
        'title' => 'Haydovchilar uchun kirish',
        'phone' => 'Telefon',
        'password' => 'Parol',
        'submit' => 'Kirish',
        'failed' => 'Telefon raqami yoki parol noto\'g\'ri.',
        'logout' => 'Chiqish',
        'hint' => 'Login va parolni operator beradi.',
    ],

    'nav' => [
        'back' => 'Orqaga',
        'today' => 'Bugun',
    ],

    'list' => [
        'empty' => 'Bu kunga transferlar yo\'q',
        'passengers' => ':count yo\'lovchi',
    ],

    'show' => [
        'title' => 'Transfer №:number',
        'date' => 'Sana va vaqt',
        'destination' => 'Manzil',
        'pickup' => 'Olib ketish joyi',
        'terminal' => 'Terminal / izoh',
        'city' => 'Shahar',
        'pax' => 'Yo\'lovchilar',
        'passenger' => 'Yo\'lovchi',
        'nameplate' => 'Tablichka',
        'mark' => 'Avtomobil',
        'transport' => 'Klass',
        'comment' => 'Izoh',
        'open_map' => 'Xaritada ochish',
        'status' => 'Holat',
    ],

    'flow' => [
        'closed' => 'Transfer operator tomonidan yopilgan — holatni o\'zgartirib bo\'lmaydi.',
        'stale' => 'Holat allaqachon o\'zgargan. Sahifa yangilandi.',
        'updated' => 'Holat: :status',
        'finished' => 'Safar yakunlandi',
        'confirm_title' => 'Safarni yakunlaysizmi?',
        'confirm_text' => 'Yakunlangandan so\'ng holatni o\'zingiz o\'zgartira olmaysiz.',
        'confirm_yes' => 'Ha, yakunlash',
        'confirm_no' => 'Bekor qilish',
    ],
];
