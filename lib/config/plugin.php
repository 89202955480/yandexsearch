<?php

return array(
    'name' => 'Яндекс.Поиск',
    'description' => 'Повышает конверсию. Найдется ВСЕ!',
    'vendor' => 985310,
    'version' => '1.0.1',
    'img' => 'img/yandexsearch.png',
    'shop_settings' => true,
    'frontend' => true,
    'handlers' => array(
        'frontend_search' => 'frontendSearch',
    ),
);
//EOF
