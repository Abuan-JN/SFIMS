<?php

namespace App;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): self
    {
        $this->routes[$method][$path] = $handler;
        return $this;
    }

    public function addMiddleware(string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function dispatch(string $uri, string $method): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = trim($uri, '/');
        
        // Run middleware
        foreach ($this->middleware as $middlewareClass) {
            $middleware = new $middlewareClass();
            if (!$middleware->handle()) {
                return;
            }
        }

        // Find matching route
        foreach ($this->routes[$method] ?? [] as $routePath => $handler) {
            $pattern = $this->convertToRegex($routePath);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->callHandler($handler, $matches);
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo "404 - Page not found";
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z]+)\}/', '(?P<$1>[a-zA-Z0-9_-]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function callHandler(array $handler, array $params): void
    {
        [$controllerName, $method] = $handler;
        
        $controllerClass = "App\\Controllers\\{$controllerName}";
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller {$controllerClass} not found");
        }

        $controller = new $controllerClass();
        
        if (!method_exists($controller, $method)) {
            throw new \Exception("Method {$method} not found in {$controllerClass}");
        }

        call_user_func_array([$controller, $method], $params);
    }
}
