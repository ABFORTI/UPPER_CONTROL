<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/quotations', 'GET', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
]);

$response = $kernel->handle($request);

echo "STATUS: {$response->getStatusCode()}\n";
echo $response->getContent() . "\n";

$kernel->terminate($request, $response);
