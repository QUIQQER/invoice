<?php

namespace QUI\AI\MCP;

use Mcp\Schema\Result\CallToolResult;

if (!class_exists(ToolHelper::class)) {
    class ToolHelper
    {
        private static mixed $LastException = null;

        public static function parseExceptionToResult(mixed $e): CallToolResult
        {
            self::$LastException = $e;

            return new CallToolResult();
        }

        public static function getLastException(): mixed
        {
            return self::$LastException;
        }
    }
}
