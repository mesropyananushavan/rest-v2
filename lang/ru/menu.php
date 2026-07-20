<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Меню',
        'eyebrow' => 'Администрирование',
        'heading' => 'Меню',
        'subtitle' => 'Управляйте категориями и позициями меню филиала.',
    ],
    'categories' => [
        'heading' => 'Категории',
        'create_title' => 'Создать категорию',
        'edit_title' => 'Редактировать категорию',
    ],
    'items' => [
        'heading' => 'Позиции',
        'create_title' => 'Создать позицию',
        'edit_title' => 'Редактировать позицию',
    ],
    'fields' => [
        'actions' => 'Действия',
        'active' => 'Активно',
        'category' => 'Категория',
        'currency' => 'Валюта',
        'description_en' => 'Описание, английский',
        'description_hy' => 'Описание, армянский',
        'description_ru' => 'Описание, русский',
        'name' => 'Название',
        'name_en' => 'Название, английский',
        'name_hy' => 'Название, армянский',
        'name_ru' => 'Название, русский',
        'price' => 'Цена',
        'price_major' => 'Цена',
        'price_minor' => 'Цена в минорных единицах',
        'sort_order' => 'Порядок сортировки',
    ],
    'actions' => [
        'archive' => 'Архивировать',
        'back' => 'Назад',
        'cancel' => 'Отмена',
        'create' => 'Создать',
        'create_category' => 'Создать категорию',
        'create_item' => 'Создать позицию',
        'edit' => 'Редактировать',
        'hide_archived' => 'Скрыть архив',
        'restore' => 'Восстановить',
        'save' => 'Сохранить',
        'show_archived' => 'Показать архив',
    ],
    'status' => [
        'active' => 'Активно',
        'archived' => 'В архиве',
        'inactive' => 'Неактивно',
    ],
    'confirm' => [
        'archive_category_title' => 'Архивировать категорию?',
        'archive_category_message' => 'Категория и её текущие позиции будут скрыты из обычной работы. Суперадмин сможет восстановить их позже.',
        'archive_item_title' => 'Архивировать позицию?',
        'archive_item_message' => 'Позиция будет скрыта из обычной работы с меню. Суперадмин сможет восстановить её позже.',
    ],
    'empty' => [
        'categories' => 'Категорий пока нет.',
        'items' => 'Позиций пока нет.',
    ],
    'flash' => [
        'category_created' => 'Категория создана.',
        'category_updated' => 'Категория обновлена.',
        'category_archived' => 'Категория архивирована.',
        'category_restored' => 'Категория восстановлена.',
        'item_created' => 'Позиция создана.',
        'item_updated' => 'Позиция обновлена.',
        'item_archived' => 'Позиция архивирована.',
        'item_restored' => 'Позиция восстановлена.',
    ],
    'placeholders' => [
        'select_category' => 'Выберите категорию',
    ],
];
