<?php

namespace App\EventSubscriber;

use App\Security\SessionExpirationHandler;
use App\Security\User\ApiUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class ExpiredJwtSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private SessionExpirationHandler $sessionExpirationHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 7: right after the firewall (8), so the token is already loaded.
        return [KernelEvents::REQUEST => ['checkTokenExpiration', 7]];
    }

    public function checkTokenExpiration(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->sessionExpirationHandler->isExcluded($request)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof ApiUser || !$user->isTokenExpired()) {
            return;
        }

        $event->setResponse($this->sessionExpirationHandler->expire($request));
    }
}
