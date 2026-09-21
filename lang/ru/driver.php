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
        'status' => 'Статус',
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
