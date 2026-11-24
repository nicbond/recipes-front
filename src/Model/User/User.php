<?php

namespace App\Model\User;

final readonly class User
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $id,
        private string $email,
        private array $roles,
        private ?\DateTimeImmutable $createdAt = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
