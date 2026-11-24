<?php

namespace App\Service;

use App\Exception\ApiUnauthorizedException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ApiService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        #[Autowire('%env(resolve:API_BACK_URL)%')]
        private readonly string $apiBackUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ApiUnauthorizedException
     * @throws TransportExceptionInterface
     */
    public function request(
        string $method,
        string $path,
        array $options = [],
        ?string $token = null,
    ): ResponseInterface {
        /** @var array<string, string> $headers */
        $headers = $options['headers'] ?? [];

        $headers['Accept'] = 'application/json';
        $headers['Accept-Language'] = 'fr';

        if (null !== $token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $options['headers'] = $headers;

        $response = $this->client->request(
            $method,
            rtrim($this->apiBackUrl, '/').'/'.ltrim($path, '/'),
            $options,
        );

        // Only authenticated calls: a 401 on the login call means "wrong credentials", not "expired session".
        if (null !== $token && Response::HTTP_UNAUTHORIZED === $response->getStatusCode()) {
            throw new ApiUnauthorizedException();
        }

        return $response;
    }

    public function postLogin(string $email, string $password): ResponseInterface
    {
        return $this->request('POST', '/login_check', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);
    }
}
