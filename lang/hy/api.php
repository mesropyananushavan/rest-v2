<?php

declare(strict_types=1);

return [
    'errors' => [
        'forbidden' => 'Դուք չունեք այս գործողությունը կատարելու թույլտվություն։',
        'not_found' => 'Պահանջված ռեսուրսը չի գտնվել։',
        'unauthenticated' => 'Պահանջվում է մուտք գործել։',
        'validation' => 'Փոխանցված հարցման պարամետրերը վավեր չեն։',
    ],
    'fields' => [
        'category_id' => 'կատեգորիա',
        'page' => 'էջ',
        'per_page' => 'դիրքեր էջում',
        'search' => 'որոնում',
    ],
    'validation' => [
        'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
        'max' => ':attribute դաշտը չի կարող լինել :max նիշից երկար։',
        'min' => ':attribute դաշտը պետք է լինի առնվազն :min։',
        'string' => ':attribute դաշտը պետք է լինի տեքստ։',
    ],
];
