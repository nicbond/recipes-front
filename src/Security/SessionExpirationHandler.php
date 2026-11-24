<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Single place that closes an expired or rejected session.
 */
final readonly class SessionExpirationHandler
{
    use TargetPathTrait;

    private const MESSAGE = 'Votre session a expiré pour des raisons de sécurité. Veuillez vous reconnecter.';

    /** Must match the firewall name declared in security.yaml. */
    private const FIREWALL_NAME = 'main';

    private const EXCLUDED_ROUTES = ['app_login', 'app_logout'];

    public function __construct(private TokenStorageInterface $tokenStorage, private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Routes that must never trigger a session closing (avoids redirect loops
     * and lets an expired user log out).
     */
    public function isExcluded(Request $request): bool
    {
        return \in_array($request->attributes->get('_route'), self::EXCLUDED_ROUTES, true);
    }

    public function expire(Request $request): RedirectResponse
    {
        $this->tokenStorage->setToken(null);

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->invalidate();

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('warning', self::MESSAGE);
            }

            // Saved after invalidate(), otherwise the session would be wiped.
            if ($this->canBeRestored($request)) {
                $this->saveTargetPath($session, self::FIREWALL_NAME, $request->getRequestUri());
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    /**
     * Only a plain page view can be replayed after the login: a POST cannot be
     * redirected to, and an AJAX call is not a page.
     */
    private function canBeRestored(Request $request): bool
    {
        if (!$request->isMethod('GET') || $request->isXmlHttpRequest()) {
            return false;
        }

        $uri = $request->getRequestUri();

        // Relative URL only: refuses "//host" and "/\host" (open redirect).
        return str_starts_with($uri, '/')
            && !str_starts_with($uri, '//')
            && !str_contains($uri, '\\');
    }
}
