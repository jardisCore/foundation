<?php

declare(strict_types=1);

namespace JardisCore\Foundation\Context;

use Exception;
use JardisPsr\Foundation\BoundedContextInterface;
use JardisPsr\Foundation\DomainKernelInterface;
use JardisPsr\Foundation\ResponseInterface;

/**
 * Base Bounded Context
 *
 * Provides request/response handling for bounded context communication.
 */
class BoundedContext implements BoundedContextInterface
{
    private DomainKernelInterface $domainKernel;
    private ?Request $domainRequest = null;
    private ?ResponseInterface $response = null;

    public function __construct(DomainKernelInterface $domainKernel, ?Request $domainRequest = null)
    {
        $this->domainKernel = $domainKernel;
        $this->domainRequest = $domainRequest;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @throws Exception
     * @return T|null
     */
    public function handle(string $className, mixed ...$parameters): mixed
    {
        try {
            $this->setRequest($parameters);
            $version = $this->getRequest() ? $this->getRequest()->version : '';

            $factory = $this->getResource()->getFactory();
            if ($factory === null) {
                throw new Exception('Factory not available');
            }

            // Check if className implements BoundedContextInterface
            if (is_subclass_of($className, BoundedContextInterface::class)) {
                /** @var T|null */
                return $factory->get(
                    $className,
                    $version,
                    $this->getResource(),
                    $this->getRequest(),
                    $parameters
                );
            }

            return $factory->get($className, $version, ...$parameters);
        } catch (Exception $e) {
            $logger = $this->getResource()->getLogger();
            if ($logger !== null) {
                $logger->error($e->getMessage(), ['exception' => $e]);
            }
            throw $e;
        }
    }

    protected function getResource(): DomainKernelInterface
    {
        return $this->domainKernel;
    }

    protected function getRequest(): ?Request
    {
        return $this->domainRequest;
    }

    /**
     * @param array<string, mixed>|null $data
     * @throws Exception
     */
    protected function getResponse(?array $data = []): ResponseInterface
    {
        if ($this->response === null) {
            $context = basename(str_replace('\\', '/', get_class($this)));
            $request = $this->getRequest();
            if ($request === null) {
                throw new Exception('Domain request is required to create response');
            }
            $factory = $this->getResource()->getFactory();
            if ($factory === null) {
                throw new Exception('Factory not available');
            }
            $response = $factory->get(
                Response::class,
                $request->version,
                $context
            );
            if (!$response instanceof ResponseInterface) {
                throw new Exception('Factory did not return a valid ResponseInterface');
            }

            // Set data if provided
            if ($data !== null && !empty($data)) {
                $response->setData($data);
            }

            $this->response = $response;
        }

        return $this->response;
    }

    /**
     * Creates an error response from an exception.
     *
     * @param Exception $e They caught exception
     * @return ResponseInterface
     * @throws Exception If a response cannot be created (no request/factory)
     */
    protected function getErrorResponse(Exception $e): ResponseInterface
    {
        $errorData = [
            'type' => (new \ReflectionClass($e))->getShortName(),
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 500
        ];

        // Add stack trace in development mode
        if (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true') {
            $errorData['trace'] = $e->getTraceAsString();
        }

        // Reset cached response to allow error response creation
        $this->response = null;

        return $this->getResponse($errorData);
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function setRequest(array &$parameters): void
    {
        if ($this->domainRequest) {
            return;
        }

        foreach ($parameters as $index => $parameter) {
            if ($parameter instanceof Request) {
                $this->domainRequest = $parameter;
                unset($parameters[$index]);
                break;
            }
        }
    }
}
