<?php

namespace QUI\REST;

use Slim\App;

if (!class_exists(Server::class)) {
    class Server
    {
        public function getSlim(): App
        {
            return new App();
        }
    }
}
