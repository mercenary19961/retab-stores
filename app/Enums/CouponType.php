<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage'; // value = percent off (e.g. 15)
    case Fixed = 'fixed';           // value = flat amount off in SAR
}
