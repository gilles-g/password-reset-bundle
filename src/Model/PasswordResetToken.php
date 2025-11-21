<?php

namespace GillesG\PasswordExpirationBundle\Model;

class PasswordResetToken
{
    public function __construct(
        private readonly string $token,
        private readonly string $selector,
        private readonly \DateTimeInterface $expiresAt,
        private readonly object $user
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function isExpired(): bool
    {
        return new \DateTime() > $this->expiresAt;
    }
}
