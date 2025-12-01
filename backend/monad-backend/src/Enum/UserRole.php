<?php

namespace App\Enum;

enum UserRole: string
{
    case USER = 'ROLE_USER';
    case SUPERADMIN = 'ROLE_SUPERADMIN';
}
