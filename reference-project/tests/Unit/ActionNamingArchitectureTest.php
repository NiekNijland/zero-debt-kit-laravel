<?php

declare(strict_types=1);

arch('actions are named after what they do, without an Action suffix')
    ->expect('App\Modules\*\Actions')
    ->classes()
    ->not->toHaveSuffix('Action');
