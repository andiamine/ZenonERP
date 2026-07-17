<?php

namespace Modules\Dummy\Providers;

use App\Foundation\Modules\ModuleServiceProvider;

class DummyServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Dummy';

    protected string $nameLower = 'dummy';
}
