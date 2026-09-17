<?php

declare(strict_types=1);

namespace App\Support\Calendar;

enum IcsMethod: string
{
    case Publish = 'PUBLISH';
    case Cancel = 'CANCEL';
}
