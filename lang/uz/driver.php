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
        'client' => 'Mijoz',
        'call_client' => 'Qo\'ng\'iroq',
        'status' => 'Holat',
    ],

    // Dispatcher accounts: see every transfer and every driver, may set any status.
    'dispatcher' => [
        'tab_transfers' => 'Transferlar',
        'tab_drivers' => 'Haydovchilar',
        'driver' => 'Haydovchi',
        'no_driver' => 'Haydovchisiz',
        'call' => 'Qo\'ng\'iroq qilish',
        'save' => 'Saqlash',
        'no_drivers' => 'Haydovchilar hali yo\'q',
        'trips_today' => 'Bugun: :count',
        'no_trips' => 'Bugun bo\'sh',
        'disabled' => 'O\'chirilgan',
    ],

    // Money the driver spent on a trip (amounts are always in sums).
    'expenses' => [
        'title' => 'Xarajatlar',
        'add' => 'Xarajat qo\'shish',
        'empty' => 'Hozircha xarajatlar yo\'q',
        'total' => 'Jami: :amount',
        'type' => 'Xarajat turi',
        'type_placeholder' => 'Turini tanlang',
        'amount' => 'Narxi',
        'receipt' => 'Chek rasmi',
        'receipt_hint' => 'JPG, PNG yoki WebP, 10 MB gacha',
        'photo' => 'Rasm',
        'delete' => 'O\'chirish',
        'delete_title' => 'Xarajat o\'chirilsinmi?',
        'delete_text' => 'Chek rasmi ham o\'chiriladi.',
        'delete_yes' => 'Ha, o\'chirish',
        'added' => 'Xarajat qo\'shildi va tekshiruvga yuborildi.',
        'deleted' => 'Xarajat o\'chirildi.',
        'cannot_delete' => 'Bu xarajatni endi o\'chirib bo\'lmaydi.',
        'added_by' => 'Qo\'shgan: :name',
        'rejected_note' => 'Rad etilish sababi: :note',
        'types' => [
            'driver_services' => 'Haydovchi xizmati',
            'parking' => 'Parkovka',
            'fuel' => 'Yoqilg\'i quyish',
            'road' => 'Yo\'l',
            'wash' => 'Avtomoyka',
            'water' => 'Suv',
            'other' => 'Boshqa',
        ],
        'statuses' => [
            'new' => 'Tekshiruvda',
            'approved' => 'Qabul qilindi',
            'rejected' => 'Rad etildi',
        ],
        'errors' => [
            'type' => 'Xarajat turini tanlang.',
            'amount' => 'Summani butun son bilan kiriting, masalan 150 000.',
            'receipt_missing' => 'Chek rasmini qo\'shing. Agar rasm tanlangan bo\'lsa, u juda katta bo\'lishi mumkin.',
            'receipt_format' => 'Rasm JPG, PNG yoki WebP formatida bo\'lishi kerak.',
            'receipt_size' => 'Rasm juda katta (10 MB dan oshmasligi kerak).',
        ],
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
