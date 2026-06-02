<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Handler;

use JardisAdapter\Filesystem\FilesystemService;
use JardisSupport\Contract\Filesystem\FilesystemServiceInterface;

/**
 * Provides the FilesystemService factory.
 *
 * Requires jardisadapter/filesystem. No ENV needed — the service is a stateless factory.
 */
final class FilesystemHandler
{
    public function __invoke(): ?FilesystemServiceInterface
    {
        if (!class_exists(FilesystemService::class)) {
            return null;
        }

        return new FilesystemService();
    }
}
