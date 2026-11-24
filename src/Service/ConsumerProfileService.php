<?php

namespace App\Service;

use App\Exception\ApiUnauthorizedException;
use App\Model\User\Identity;
use App\Security\User\ApiUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ConsumerProfileService
{
    public function __construct(private ApiService $apiService)
    {
    }

    /**
     * Retrieves the identity of the logged-in consumer.
     *
     * @throws ApiUnauthorizedException
     * @throws ExceptionInterface
     * @throws \UnexpectedValueException
     */
    public function getIdentity(ApiUser $apiUser): Identity
    {
        $response = $this->apiService->request(
            'GET',
            '/me/profile',
            token: $apiUser->getToken(),
        );

        return $this->createIdentityFromResponse($response);
    }

    /**
     * Updates the identity of the logged-in consumer.
     *
     * @return list<array{propertyPath: string, message: string}>
     *
     * @throws ApiUnauthorizedException
     * @throws ExceptionInterface
     * @throws \UnexpectedValueException
     */
    public function updateIdentity(ApiUser $apiUser, Identity $identity): array
    {
        $response = $this->apiService->request(
            'PATCH',
            '/me/profile',
            [
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
                'json' => [
                    'identity' => [
                        'gender' => $identity->getGender(),
                        'firstname' => $identity->getFirstname(),
                        'lastname' => $identity->getLastname(),
                        'birthdate' => $identity->getBirthdate()?->format('Y-m-d'),
                    ],
                ],
            ],
            $apiUser->getToken(),
        );

        if (Response::HTTP_UNPROCESSABLE_ENTITY === $response->getStatusCode()) {
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);

            $violations = $payload['violations'] ?? null;

            if (!is_array($violations)) {
                throw new \UnexpectedValueException('La réponse de validation de l’API est invalide.');
            }

            $errors = [];

            foreach ($violations as $violation) {
                if (
                    !is_array($violation)
                    || !isset($violation['propertyPath'], $violation['message'])
                    || !is_string($violation['propertyPath'])
                    || !is_string($violation['message'])
                ) {
                    continue;
                }

                $errors[] = [
                    'propertyPath' => $violation['propertyPath'],
                    'message' => $violation['message'],
                ];
            }

            if ([] === $errors) {
                throw new \UnexpectedValueException('La réponse de validation de l’API ne contient aucune violation exploitable.');
            }

            return $errors;
        }

        $response->getContent();

        return [];
    }

    /**
     * Constructs an identity based on the API response.
     *
     * @throws ExceptionInterface
     * @throws \UnexpectedValueException
     */
    private function createIdentityFromResponse(ResponseInterface $response): Identity
    {
        /** @var array<string, mixed> $payload */
        $payload = $response->toArray();

        $identity = $payload['identity'] ?? null;

        if (!is_array($identity)) {
            throw new \UnexpectedValueException('La réponse de l’API ne contient pas d’identité valide.');
        }

        $birthdate = null;

        if (isset($identity['birthdate']) && is_string($identity['birthdate'])) {
            $birthdate = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $identity['birthdate'],
            );

            if (
                false === $birthdate
                || $birthdate->format('Y-m-d') !== $identity['birthdate']
            ) {
                throw new \UnexpectedValueException('L’API a retourné une date de naissance invalide.');
            }
        }

        return new Identity(
            gender: isset($identity['gender']) && is_string($identity['gender'])
                ? $identity['gender']
                : null,
            firstname: isset($identity['firstname']) && is_string($identity['firstname'])
                ? $identity['firstname']
                : null,
            lastname: isset($identity['lastname']) && is_string($identity['lastname'])
                ? $identity['lastname']
                : null,
            birthdate: $birthdate,
        );
    }
}
