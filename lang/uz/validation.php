<?php

// NOTE: Uzbek (Latin) — please have a native speaker review.
// Only the rules the driver login form can trigger are translated. Every other rule falls back to
// Russian (config/app.php fallback_locale), so a message is never shown as a raw key.
return [
    'required' => ':attribute kiritilishi shart.',
    'string' => ':attribute matn bo\'lishi kerak.',
    'max' => [
        'string' => ':attribute :max ta belgidan oshmasligi kerak.',
    ],
];
