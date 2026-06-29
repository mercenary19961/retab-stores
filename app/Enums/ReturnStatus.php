<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested'; // customer filed a defect/damage return
    case Approved = 'approved';   // admin verified the issue
    case Rejected = 'rejected';   // not eligible
    case Exchanged = 'exchanged'; // resolved by exchange
    case Refunded = 'refunded';   // resolved by refund
}
