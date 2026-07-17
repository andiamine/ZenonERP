<?php

use App\Foundation\Modules\Middleware\EnsureModuleEnabled;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('404s module routes on the central domain', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $this->getJson('http://app.zenonerp.test/api/v1/dummy/items')->assertNotFound();
    $this->getJson('http://ghost.zenonerp.test/api/v1/dummy/items')->assertNotFound();
});

it('404s for a tenant that never enabled the module', function () {
    installModule('dummy');
    createTenant('acme');
    $beta = createTenant('beta');

    assertModuleInvisibleFor($beta, '/api/v1/dummy/items');
});

it('aborts 404 outside any tenant context (direct middleware probe)', function () {
    $middleware = new EnsureModuleEnabled(app(ModuleRegistry::class));

    expect(fn () => $middleware->handle(Request::create('/api/v1/dummy/items'), fn () => response('ok'), 'dummy'))
        ->toThrow(NotFoundHttpException::class);
});
