<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Handler;

use Closure;
use JardisAdapter\Mailer\Config\Encryption;
use JardisAdapter\Mailer\Config\SmtpConfig;
use JardisAdapter\Mailer\Mailer;
use JardisSupport\Contract\Mailer\MailerInterface;

/**
 * Builds a Mailer from ENV values.
 *
 * Requires jardisadapter/mailer. Configuration via MAIL_* environment variables.
 */
final class MailerHandler
{
    /** @param Closure(string): mixed $env */
    public function __invoke(Closure $env): ?MailerInterface
    {
        if (!class_exists(Mailer::class)) {
            return null;
        }

        $host = $env('mail_host');
        if ($host === null) {
            return null;
        }

        return new Mailer(new SmtpConfig(
            host: (string) $host,
            port: (int) ($env('mail_port') ?? 587),
            encryption: Encryption::from((string) ($env('mail_encryption') ?? 'tls')),
            username: $env('mail_username') !== null ? (string) $env('mail_username') : null,
            password: $env('mail_password') !== null ? (string) $env('mail_password') : null,
            timeout: (int) ($env('mail_timeout') ?? 30),
            fromAddress: $env('mail_from_address') !== null ? (string) $env('mail_from_address') : null,
            fromName: $env('mail_from_name') !== null ? (string) $env('mail_from_name') : null,
        ));
    }
}
