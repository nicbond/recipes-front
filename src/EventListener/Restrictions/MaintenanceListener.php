<?php

namespace App\EventListener\Restrictions;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class MaintenanceListener
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        // 1. If maintenance is disabled: full access for everyone.
        if (empty($_ENV['ACTIVE_MAINTENANCE_PAGE'])) {
            $request->attributes->set('has_full_access', true);

            return;
        }

        // 2. If we are already on the maintenance page, do nothing to avoid a loop.
        if ('/maintenance' === $request->getPathInfo()) {
            return;
        }

        $clientIp = $request->getClientIp();
        $allowedIp = [];

        if (isset($_ENV['ALLOWED_IP']) && is_string($_ENV['ALLOWED_IP']) && '' !== $_ENV['ALLOWED_IP']) {
            $allowedIp = array_map('trim', explode(',', $_ENV['ALLOWED_IP']));
        }

        // 3. If maintenance is active BUT the IP is authorized: full access.
        if (in_array($clientIp, $allowedIp, true)) {
            $request->attributes->set('has_full_access', true);

            return;
        }

        // 4. If maintenance is active and the IP is unauthorized: no access and redirection.
        $request->attributes->set('has_full_access', false);

        $maintenanceUrl = $this->urlGenerator->generate('maintenance');
        $response = new RedirectResponse($maintenanceUrl, Response::HTTP_FOUND);

        $event->setResponse($response);
    }
}
