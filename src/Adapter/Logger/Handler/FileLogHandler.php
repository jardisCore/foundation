<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Formatter\LogHumanFormat;
use JardisAdapter\Logger\Formatter\LogJsonFormat;
use JardisAdapter\Logger\Formatter\LogLineFormat;
use JardisAdapter\Logger\Handler\LogFile;

/**
 * File Log Handler
 *
 * Creates file-based log handler from configuration.
 *
 * Required options: 'path'
 */
class FileLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $path = $config->getOption('path');
        if ($path === null || !is_string($path) || trim($path) === '') {
            throw new InvalidArgumentException(
                "File handler requires 'path' option. Example: [type=>file, path=>/var/log/app.log]"
            );
        }

        $handler = new LogFile($config->level, $path);

        // Set format if provided
        if ($config->format !== null) {
            $handler->setFormat($this->createFormatter($config->format));
        }

        return $handler;
    }

    /**
     * @param string $format
     * @return LogFormatInterface
     */
    private function createFormatter(string $format): LogFormatInterface
    {
        return match (strtolower($format)) {
            'line', 'text' => new LogLineFormat(),
            'human' => new LogHumanFormat(),
            default => new LogJsonFormat(),
        };
    }
}
