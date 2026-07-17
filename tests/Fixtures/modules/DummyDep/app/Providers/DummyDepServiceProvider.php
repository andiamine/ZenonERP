<?php

namespace Modules\DummyDep\Providers;

use App\Foundation\Modules\ModuleServiceProvider;

class DummyDepServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'DummyDep';

    protected string $nameLower = 'dummydep';
}
