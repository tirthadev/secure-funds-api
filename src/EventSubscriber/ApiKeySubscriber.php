<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ApiKeySubscriber implements EventSubscriberInterface
{
    public function __construct(private string $apiKey)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['authenticate', 20]];
    }

    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            $providedKey = (string) $request->headers->get('X-API-Key', '');
            if ($providedKey === '' || !hash_equals($this->apiKey, $providedKey)) {
                $event->setResponse(new JsonResponse([
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'A valid X-API-Key header is required.',
                    ],
                ], 401));
            }
        }
    }
}
