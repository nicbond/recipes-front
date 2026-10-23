<?php

namespace App\Security\User;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiUser implements UserInterface, EquatableInterface
{
    /**
     * @param non-empty-string $email
     * @param list<string>     $roles
     */
    public function __construct(private string $email, private array $roles, private string $token, private readonly ?int $expiresAt = null)
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isTokenExpired(): bool
    {
        return null === $this->expiresAt || time() >= $this->expiresAt;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_CONSUMER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Allows Symfony to compare the user in the session with future requests.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof ApiUser) {
            return false;
        }

        return $this->getUserIdentifier() === $user->getUserIdentifier()
            && $this->getRoles() === $user->getRoles();
    }
}
