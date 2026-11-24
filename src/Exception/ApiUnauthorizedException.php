<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when the API rejects the access token of an authenticated call.
 *
 * It deliberately extends neither the HttpClient exceptions nor
 * \UnexpectedValueException: controllers catch those, and they must not
 * swallow this one, which is handled by ApiUnauthorizedSubscriber.
 */
final class ApiUnauthorizedException extends \RuntimeException
{
    public function __construct(
        string $message = 'L’API a rejeté le token d’accès.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, Response::HTTP_UNAUTHORIZED, $previous);
    }
}
