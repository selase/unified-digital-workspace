<?php

declare(strict_types=1);

return [
    'name' => 'Institutional Intranet',
    'slug' => 'intranet',
    'description' => 'Institutional Intranet Portal, Internal News & CMS Scoping, Upcoming Events, Message Boards, and Controlled Document Access',
    'version' => '1.0.0',
    'provider' => App\Modules\Intranet\Providers\IntranetServiceProvider::class,
    'permissions' => [
        'intranet.view',
        'intranet.manage',
        'intranet.events.manage',
        'intranet.boards.post',
    ],
];
