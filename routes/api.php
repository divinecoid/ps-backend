<?php

use App\Http\Controllers\Auth\LoginController;


Route::post('/auth/login', [LoginController::class, 'login']);