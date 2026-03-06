<?php

namespace App\Enums;

enum ConfigDataType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case DECIMAL = 'decimal';
    case STRING = 'string';
}
