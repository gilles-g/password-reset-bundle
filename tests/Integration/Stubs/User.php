<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration\Stubs;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private ?int $id = null;
    private string $username = 'testuser';
    private string $password = 'hashed_password';
    private array $roles = ['ROLE_USER'];
    private ?\DateTimeInterface $passwordUpdatedAt = null;
    private array $passwordHistory = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPasswordUpdatedAt(): ?\DateTimeInterface
    {
        return $this->passwordUpdatedAt;
    }

    public function setPasswordUpdatedAt(?\DateTimeInterface $passwordUpdatedAt): self
    {
        $this->passwordUpdatedAt = $passwordUpdatedAt;
        return $this;
    }

    public function getPasswordHistory(): array
    {
        return $this->passwordHistory;
    }

    public function setPasswordHistory(array $passwordHistory): self
    {
        $this->passwordHistory = $passwordHistory;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase
    }
}
