<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Вход',
        'heading' => 'Вход в SmartRest',
        'subtitle' => 'Используйте slug ресторана, рабочую электронную почту и пароль.',
        'submit' => 'Войти',
    ],
    'logout' => [
        'submit' => 'Выйти',
    ],
    'fields' => [
        'tenant_slug' => 'Slug ресторана',
        'email' => 'Электронная почта',
        'password' => 'Пароль',
    ],
    'failed' => 'Неверная почта или пароль, либо пользователь неактивен.',
    'tenant_suspended' => 'Аккаунт этого ресторана не активен. Обратитесь в поддержку SmartRest.',
];
