<?php

namespace Slim;

if (!class_exists(App::class)) {
    class App
    {
        /**
         * @param callable(\Slim\Routing\RouteCollectorProxy): void $callable
         */
        public function group(string $pattern, callable $callable): void
        {
        }
    }
}
