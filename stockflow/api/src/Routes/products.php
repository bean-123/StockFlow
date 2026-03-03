<?php

//Products routes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

//Get all products
$app->get('/api/products', function (Request $request, Response $response) {
    $auth = new SupbaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);

    $response->getBody()->write(json_encode($products));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());