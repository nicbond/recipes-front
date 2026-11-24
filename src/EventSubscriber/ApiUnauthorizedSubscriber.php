<?php

namespace App\EventSubscriber;

use App\Exception\ApiUnauthorizedException;
use App\Security\SessionExpirationHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Logs the user out when the API rejects the access token (revoked, key rotated, expired in between).
 */
final readonly class ApiUnauthorizedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SessionExpirationHandler $sessionExpirationHandler,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 10: runs before Symfony's ErrorListener (0), which would log
        // "Uncaught PHP Exception" for an error that is handled here.
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isApiUnauthorized($event->getThrowable())) {
            return;
        }

        $request = $event->getRequest();

        if ($this->sessionExpirationHandler->isExcluded($request)) {
            return;
        }

        $this->logger->warning(
            'The API rejected the access token: closing the session.',
            ['route' => $request->attributes->get('_route')],
        );

        $event->setResponse($this->sessionExpirationHandler->expire($request));
    }

    /**
     * The exception can be wrapped (for example by Twig when thrown during rendering).
     */
    private function isApiUnauthorized(\Throwable $throwable): bool
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ApiUnauthorizedException) {
                return true;
            }
        }

        return false;
    }
}
