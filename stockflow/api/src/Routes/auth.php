<?php

// This replaces the auth logic from 11-authentication.php
// Key differences: No session, API based middleware management of token

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// Public endpoint for Google
$app->get('/api/auth/login-url', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $url = $auth->getGoogleSignInUrl();

    $response->getBody()->write(json_encode(['url' => $url]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Protected endpoint for user info
$app->get('/api/auth/user', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $user = $auth->getUser();

    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(['user' => $user]));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());