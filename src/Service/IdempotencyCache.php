<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class IdempotencyCache
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function get(string $key): ?array
    {
        try {
            $item = $this->cache->getItem($this->cacheKey($key));

            return $item->isHit() ? $item->get() : null;
        } catch (Throwable $exception) {
            $this->logger->warning('Idempotency cache read failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function put(string $key, array $response): void
    {
        try {
            $item = $this->cache->getItem($this->cacheKey($key));
            $item->set($response);
            $item->expiresAfter(86_400);
            $this->cache->save($item);
        } catch (Throwable $exception) {
            $this->logger->warning('Idempotency cache write failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function cacheKey(string $key): string
    {
        return 'transfer.'.hash('sha256', $key);
    }
}
