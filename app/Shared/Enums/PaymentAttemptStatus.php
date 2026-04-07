<?php

namespace App\Shared\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
}
