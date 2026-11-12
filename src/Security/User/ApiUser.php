<?php

namespace App\Security\User;

use App\Model\User\User;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiUser implements UserInterface, EquatableInterface
{
    public function __construct(
        private User $user,
        private string $token,
        private readonly ?int $expiresAt = null,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isTokenExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return time() >= $this->expiresAt;
    }

    public function getRoles(): array
    {
        $roles = $this->user->getRoles();
        $roles[] = 'ROLE_CONSUMER';

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $email = $this->user->getEmail();

        if ('' === $email) {
            throw new \LogicException('The authenticated user must have an email address.');
        }

        return $email;
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
