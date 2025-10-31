<?php

namespace App\Http;

use App\Http\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\HttpKernel;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        'checkrole' => RoleMiddleware::class
    ];
}