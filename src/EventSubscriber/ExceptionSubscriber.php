<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ApiException) {
            $event->setResponse(new JsonResponse([
                'error' => [
                    'code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], $exception->getStatusCode()));

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse([
                'error' => [
                    'code' => 'http_error',
                    'message' => $exception->getMessage(),
                ],
            ], $exception->getStatusCode()));

            return;
        }

        $this->logger->error('Unhandled API exception.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => 'internal_error',
                'message' => 'The request could not be processed.',
            ],
        ], 500));
    }
}
