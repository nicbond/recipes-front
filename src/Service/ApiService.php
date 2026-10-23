<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
     */
    public function request(string $method, string $path, array $options = [], ?string $token = null): ResponseInterface
    {
        /** @var array<string, string> $headers */
        $headers = $options['headers'] ?? [];

        $headers['Accept'] = 'application/json';

        if (null !== $token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $options['headers'] = $headers;

        return $this->client->request(
            $method,
            rtrim($this->apiBackUrl, '/').'/'.ltrim($path, '/'),
            $options,
        );
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
