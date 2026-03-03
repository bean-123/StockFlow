<?php

namespace StockFlow\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * AuthMiddleware - Checks for a Bearer token on protected routes.
 *
 * This does NOT validate the token itself — Supabase does that when
 * we forward the token in SupabaseAuth::makeRequest(). If the token
 * is invalid or expired, Supabase returns a 401 and we pass it through.
 *
 * All this middleware does is:
 * 1. Check the Authorization header exists
 * 2. Extract the token
 * 3. Attach it to the request so route handlers can use it
 */

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'No token provided']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Strip "Bearer " prefix, attach raw token to the request
        $token = substr($authHeader, 7);
        $request = $request->withAttribute('token', $token);

        return $handler->handle($request);
    }
}