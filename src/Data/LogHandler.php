<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Data;

/**
 * Available log handler types for LOG_HANDLERS configuration.
 */
enum LogHandler: string
{
    case File = 'file';
    case Console = 'console';
    case ErrorLog = 'errorlog';
    case Syslog = 'syslog';
    case BrowserConsole = 'browserconsole';
    case Redis = 'redis';
    case Slack = 'slack';
    case Teams = 'teams';
    case Loki = 'loki';
    case Webhook = 'webhook';
    case Null = 'null';
}
