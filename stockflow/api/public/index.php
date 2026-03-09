<?php

require __DIR__ . '/../vendor/autoload.php';
use Slim\Factory\AppFactory;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS middleware handling
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CLIENT_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Routes
require __DIR__ . '/../src/Routes/auth.php';
require __DIR__ . '/../src/Routes/products.php';
require __DIR__ . '/../src/Routes/orders.php';
require __DIR__ . '/../src/Routes/stock.php';
require __DIR__ . '/../src/Routes/ai.php';
require __DIR__ . '/../src/Routes/dashboard.php';
// Reference: src/Routes/_route_examples.php (not loaded — read it to learn the patterns)

// Slim reads the URL and HTTP method and finds the matching route. Runs any middleware, and sends the response.
$app->run();