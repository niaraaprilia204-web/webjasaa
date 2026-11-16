<?php

/**
 * Core Application Router & Dispatcher
 *
 * The application keeps both a route table (app/Routes/web.php) and a
 * controller/method/param fallback. Route-table dispatch is attempted first so
 * paths such as /operator/orders reach OrderController@operatorIndex, while
 * legacy /Controller/method URLs still work.
 */
class App
{
    protected $controller = 'Home';
    protected $method = 'index';
    protected $params = [];

    public function __construct()
    {
        if ($this->dispatchRoute()) {
            return;
        }

        $this->dispatchSegmented();
    }

    /**
     * Dispatch a request using the registered route table.
     *
     * Routes without an explicit parameter placeholder still match trailing
     * path segments (for example /customer/orders/detail/ORD-123). The longest
     * matching route wins, preventing /operator/services from swallowing
     * /operator/services/update/1.
     */
    protected function dispatchRoute()
    {
        if (!class_exists('Router')) {
            return false;
        }

        $routes = Router::getRoutes();
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (empty($routes[$requestMethod])) {
            return false;
        }

        $path = $this->requestPath();
        $matchedRoute = null;
        $matchedLength = -1;

        foreach ($routes[$requestMethod] as $routePath => $route) {
            $routePath = '/' . trim((string) $routePath, '/');
            if ($routePath === '//') {
                $routePath = '/';
            }

            $isMatch = ($routePath === '/')
                ? ($path === '/')
                : ($path === $routePath || str_starts_with($path, $routePath . '/'));

            if (!$isMatch) {
                continue;
            }

            $length = strlen($routePath);
            if ($length > $matchedLength) {
                $matchedRoute = $route;
                $matchedRoutePath = $routePath;
                $matchedLength = $length;
            }
        }

        if ($matchedRoute === null || empty($matchedRoute['action'])) {
            return false;
        }

        $action = (string) $matchedRoute['action'];
        if (strpos($action, '@') === false) {
            return false;
        }

        list($controllerName, $methodName) = explode('@', $action, 2);
        $controllerName = trim($controllerName);
        $methodName = trim($methodName);

        if ($controllerName === '' || $methodName === '') {
            return false;
        }

        $controllerFile = '../app/Controllers/' . $controllerName . '.php';
        if (!is_file($controllerFile)) {
            return false;
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            return false;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $methodName)) {
            return false;
        }

        $params = [];
        if ($path !== $matchedRoutePath) {
            $remaining = trim(substr($path, strlen($matchedRoutePath)), '/');
            if ($remaining !== '') {
                $params = array_map('urldecode', explode('/', $remaining));
            }
        }

        call_user_func_array([$controller, $methodName], $params);
        return true;
    }

    /**
     * Existing segmented fallback: /Controller/method/param.
     */
    protected function dispatchSegmented()
    {
        $url = $this->parseUrl();

        // 1. Check Controller
        if (isset($url[0]) && !empty($url[0])) {
            $rawName = $url[0];
            $controllerCandidate1 = ucfirst($rawName) . 'Controller';
            $controllerCandidate2 = ucfirst($rawName);

            if (file_exists('../app/Controllers/' . $controllerCandidate1 . '.php')) {
                $this->controller = $controllerCandidate1;
                unset($url[0]);
            } elseif (file_exists('../app/Controllers/' . $controllerCandidate2 . '.php')) {
                $this->controller = $controllerCandidate2;
                unset($url[0]);
            } else {
                $this->controller = 'NotFound';
            }
        }

        require_once '../app/Controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        // 2. Check Method
        if (isset($url[1]) && !empty($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        // 3. Parameters
        if (!empty($url)) {
            $this->params = array_values($url);
        }

        // Run controller method with parameters
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    /**
     * Normalise REQUEST_URI to an application path, stripping the configured
     * base path and an optional /public prefix.
     */
    protected function requestPath()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        $path = rawurldecode($path);
        $basePath = parse_url((string) BASEURL, PHP_URL_PATH);
        $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

        if ($basePath !== '') {
            foreach ([$basePath . '/public', $basePath] as $prefix) {
                if ($path === $prefix) {
                    $path = '/';
                    break;
                }
                if (str_starts_with($path, $prefix . '/')) {
                    $path = substr($path, strlen($prefix));
                    break;
                }
            }
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    public function parseUrl()
    {
        if (isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $url = explode('/', $url);
            return $url;
        }
        return [];
    }
}
