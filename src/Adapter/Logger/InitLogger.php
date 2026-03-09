<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Adapter\Logger;

use Exception;
use JardisAdapter\Logger\Logger;
use JardisCore\Foundation\Adapter\ConnectionProvider;
use JardisPort\Foundation\DomainKernelInterface;
use Psr\Log\LoggerInterface;

/**
 * Initialize Logger Service
 *
 * Pipeline: Load configs from ENV → Merge/Deduplicate → Create handlers → Logger
 *
 * Connection-based handlers (database, redis, kafka, rabbitmq) get connections
 * from ConnectionProvider instead of creating them internally.
 */
class InitLogger
{
    private LoggerConfigLoader $loader;
    private LoggerConfigMerger $merger;
    private LoggerHandlerFactory $factory;

    public function __construct(
        ?LoggerConfigLoader $loader = null,
        ?LoggerConfigMerger $merger = null,
        ?LoggerHandlerFactory $factory = null
    ) {
        $this->loader = $loader ?? new LoggerConfigLoader();
        $this->merger = $merger ?? new LoggerConfigMerger();
        $this->factory = $factory ?? new LoggerHandlerFactory();
    }

    /**
     * @throws Exception
     */
    public function __invoke(DomainKernelInterface $kernel, ConnectionProvider $connections): ?LoggerInterface
    {
        try {
            // 1. Load handler configurations from environment
            $configs = $this->loader->load($kernel);

            if (empty($configs)) {
                return null;
            }

            // 2. Merge and deduplicate configurations
            $mergedConfigs = $this->merger->merge($configs);

            if (empty($mergedConfigs)) {
                return null;
            }

            // 3. Create logger instance
            $context = $kernel->getEnv('APP_ENV') ?? 'app';
            $logger = new Logger((string) $context);

            // 4. Create and register all handlers
            foreach ($mergedConfigs as $config) {
                try {
                    $handler = $this->factory->create($config, $connections, $mergedConfigs);
                    $logger->addHandler($handler);
                } catch (Exception $e) {
                    throw new Exception(
                        "Failed to create log handler '{$config->type}'" .
                        ($config->name ? " (name: {$config->name})" : '') .
                        ": {$e->getMessage()}",
                        0,
                        $e
                    );
                }
            }

            return $logger;
        } catch (Exception $e) {
            throw new Exception(
                "Logger initialization failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
