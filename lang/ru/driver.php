<?php

return [
    'app_name' => 'Кабинет водителя',

    // Driver-side progress of a transfer. Labels are shown in the admin list and in the driver cabinet.
    'statuses' => [
        'assigned' => 'Назначен',
        'en_route_to_client' => 'Еду к клиенту',
        'waiting_for_client' => 'Ожидаю клиента',
        'on_the_way' => 'В пути',
        'completed' => 'Завершён',
    ],

    // Text of the button that moves a transfer INTO the given status.
    'actions' => [
        'en_route_to_client' => 'Выехал',
        'waiting_for_client' => 'На месте',
        'on_the_way' => 'Начал поездку',
        'completed' => 'Завершил',
    ],

    'auth' => [
        'title' => 'Вход для водителей',
        'phone' => 'Телефон',
        'password' => 'Пароль',
        'submit' => 'Войти',
        'failed' => 'Неверный телефон или пароль.',
        'logout' => 'Выйти',
        'hint' => 'Логин и пароль выдаёт оператор.',
    ],

    'nav' => [
        'back' => 'Назад',
        'today' => 'Сегодня',
    ],

    'list' => [
        'empty' => 'На этот день трансферов нет',
        'passengers' => ':count пасс.',
    ],

    'show' => [
        'title' => 'Трансфер №:number',
        'date' => 'Дата и время',
        'destination' => 'Куда',
        'pickup' => 'Место подачи',
        'terminal' => 'Терминал / уточнение',
        'city' => 'Город',
        'pax' => 'Пассажиры',
        'passenger' => 'Пассажир',
        'nameplate' => 'Табличка',
        'mark' => 'Автомобиль',
        'transport' => 'Класс',
        'comment' => 'Примечание',
        'open_map' => 'Открыть на карте',
        'client' => 'Клиент',
        'call_client' => 'Позвонить',
        'status' => 'Статус',
    ],

    // Dispatcher accounts: see every transfer and every driver, may set any status.
    'dispatcher' => [
        'tab_transfers' => 'Трансферы',
        'tab_drivers' => 'Водители',
        'driver' => 'Водитель',
        'no_driver' => 'Без водителя',
        'call' => 'Позвонить',
        'save' => 'Сохранить',
        'no_drivers' => 'Водителей пока нет',
        'trips_today' => 'Сегодня: :count',
        'no_trips' => 'Сегодня свободен',
        'disabled' => 'Отключён',
    ],

    // Money the driver spent on a trip (amounts are always in sums).
    'expenses' => [
        'title' => 'Затраты',
        'add' => 'Добавить затрату',
        'empty' => 'Затрат пока нет',
        'total' => 'Итого: :amount',
        'type' => 'Тип затрат',
        'type_placeholder' => 'Выберите тип',
        'amount' => 'Стоимость',
        'receipt' => 'Фото чека',
        'receipt_hint' => 'JPG, PNG или WebP, до 10 МБ',
        'photo' => 'Фото',
        'delete' => 'Удалить',
        'delete_title' => 'Удалить затрату?',
        'delete_text' => 'Фото чека тоже будет удалено.',
        'delete_yes' => 'Да, удалить',
        'added' => 'Затрата добавлена и отправлена на проверку.',
        'deleted' => 'Затрата удалена.',
        'cannot_delete' => 'Эту затрату уже нельзя удалить.',
        'added_by' => 'Добавил(а): :name',
        'rejected_note' => 'Причина отказа: :note',
        'types' => [
            'driver_services' => 'Услуги водителя',
            'parking' => 'Парковка',
            'fuel' => 'Заправка',
            'road' => 'Дорога',
            'wash' => 'Мойка',
            'water' => 'Вода',
            'other' => 'Другое',
        ],
        'statuses' => [
            'new' => 'На проверке',
            'approved' => 'Принята',
            'rejected' => 'Отклонена',
        ],
        'errors' => [
            'type' => 'Выберите тип затрат.',
            'amount' => 'Укажите сумму целым числом в сумах, например 150 000.',
            'receipt_missing' => 'Добавьте фото чека. Если фото выбрано, возможно, оно слишком большое.',
            'receipt_format' => 'Фото должно быть в формате JPG, PNG или WebP.',
            'receipt_size' => 'Фото слишком большое (не более 10 МБ).',
        ],
    ],

    'flow' => [
        'closed' => 'Трансфер закрыт оператором — изменить статус нельзя.',
        'stale' => 'Статус уже изменился. Страница обновлена.',
        'updated' => 'Статус: :status',
        'finished' => 'Поездка завершена',
        'confirm_title' => 'Завершить поездку?',
        'confirm_text' => 'После завершения статус нельзя изменить самостоятельно.',
        'confirm_yes' => 'Да, завершить',
        'confirm_no' => 'Отмена',
    ],
];
