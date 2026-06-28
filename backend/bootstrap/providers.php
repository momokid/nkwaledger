<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    ...app()->isLocal() ? [Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class] : [],

];
