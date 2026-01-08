<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case READ = 'read';
    case PREPARED = 'prepared';
    case READY_TO_SHIP = 'ready_to_ship';
    case RETRY_SHIP = 'retry_ship';
    case READY_TO_PICKUP = 'ready_to_pickup';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';
}
