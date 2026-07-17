<?php

namespace Mcp\Server;

if (!class_exists(Builder::class)) {
    class Builder
    {
        /** @var array<string, array{callback: callable, description: string, inputSchema: array<string, mixed>|null}> */
        private array $tools = [];

        /**
         * @param callable $callback
         * @param array<string, mixed>|null $inputSchema
         */
        public function addTool(
            callable $callback,
            string $name,
            string $description,
            ?array $inputSchema = null
        ): void {
            $this->tools[$name] = [
                'callback' => $callback,
                'description' => $description,
                'inputSchema' => $inputSchema
            ];
        }

        /**
         * @return array<string, array{callback: callable, description: string, inputSchema: array<string, mixed>|null}>
         */
        public function getTools(): array
        {
            return $this->tools;
        }
    }
}
