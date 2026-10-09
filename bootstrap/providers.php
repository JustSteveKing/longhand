<?php

use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IdentityServiceProvider;
use App\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    ApiServiceProvider::class,
];
