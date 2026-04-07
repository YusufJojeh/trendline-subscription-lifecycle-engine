<?php

namespace App\Shared\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function addTo(\Carbon\CarbonInterface $startsAt): \Carbon\CarbonInterface
    {
        return match ($this) {
            self::Monthly => $startsAt->copy()->addMonthNoOverflow(),
            self::Yearly => $startsAt->copy()->addYearNoOverflow(),
        };
    }
}
