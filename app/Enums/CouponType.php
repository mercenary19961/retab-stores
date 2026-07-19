<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';       // value = percent off (e.g. 15)
    case Fixed = 'fixed';                 // value = flat amount off in SAR
    case FreeShipping = 'free_shipping';  // waives the flat shipping fee; no subtotal discount
}
