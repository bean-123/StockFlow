<?php

//Orders routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

//Get all orders
$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupbaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', [
        'order' => 'created_at.desc'
    ]);

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());