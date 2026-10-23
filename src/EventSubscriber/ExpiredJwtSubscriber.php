<?php

namespace App\EventSubscriber;

use App\Security\User\ApiUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ExpiredJwtSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage, private UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['checkTokenExpiration', 7]];
    }

    public function checkTokenExpiration(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (\in_array($route, ['app_login', 'app_logout'], true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof ApiUser || !$user->isTokenExpired()) {
            return;
        }

        $this->tokenStorage->setToken(null);

        if ($request->hasSession()) {

            /** @var Session $session */
            $session = $request->getSession();

            $session->invalidate();

            $session->getFlashBag()->add(
                'warning',
                'Votre session a expiré pour des raisons de sécurité. Veuillez vous reconnecter.'
            );
        }

        $event->setResponse(
            new RedirectResponse(
                $this->urlGenerator->generate('app_login')
            )
        );
    }
}
