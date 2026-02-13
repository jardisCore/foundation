<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger\Handler;

use InvalidArgumentException;
use JardisCore\Foundation\Adapter\Logger\LoggerHandlerConfig;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogEmail;

/**
 * Email Log Handler
 *
 * Creates email log handler from configuration.
 *
 * Required options: 'to', 'from', 'subject'
 * Optional options: 'smtpHost', 'smtpPort'
 */
class EmailLogHandler
{
    public function __invoke(LoggerHandlerConfig $config): LogCommandInterface
    {
        $to = $config->getOption('to');
        $from = $config->getOption('from');
        $subject = $config->getOption('subject', 'Application Log');

        if ($to === null || !is_string($to) || trim($to) === '') {
            throw new InvalidArgumentException(
                "Email handler requires 'to' option"
            );
        }

        if ($from === null || !is_string($from) || trim($from) === '') {
            throw new InvalidArgumentException(
                "Email handler requires 'from' option"
            );
        }

        return new LogEmail(
            $config->level,
            $to,
            $from,
            is_string($subject) ? $subject : 'Application Log'
        );
    }
}
