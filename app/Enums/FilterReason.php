<?php

namespace App\Enums;

enum FilterReason: string
{
    case Reload = 'reload';
    case Bot = 'bot';
    case Datacenter = 'datacenter';
    case Foreign = 'foreign';
}
