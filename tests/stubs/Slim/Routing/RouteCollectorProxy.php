<?php

namespace Slim\Routing;

if (!class_exists(RouteCollectorProxy::class)) {
    class RouteCollectorProxy
    {
        /**
         * @param callable|array{object|string, string} $callable
         */
        public function post(string $pattern, callable|array $callable): void
        {
        }
    }
}
