<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceSensorSource: string
{
    case DEVICE = 'device';
    case SERVER = 'server';
}
