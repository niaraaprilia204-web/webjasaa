<?php

/**
 * Expressive Web Router
 */
class Router
{
    private static $routes = [];

    public static function get($uri, $action, $middleware = [])
    {
        self::$routes['GET'][$uri] = [
            'action' => $action,
            'middleware' => $middleware
        ];
    }

    public static function post($uri, $action, $middleware = [])
    {
        self::$routes['POST'][$uri] = [
            'action' => $action,
            'middleware' => $middleware
        ];
    }

    public static function getRoutes()
    {
        return self::$routes;
    }
}
