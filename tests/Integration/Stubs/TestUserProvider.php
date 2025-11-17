<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration\Stubs;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TestUserProvider implements UserProviderInterface
{
    private array $users = [];

    public function addUser(UserInterface $user): void
    {
        $this->users[$user->getUserIdentifier()] = $user;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (isset($this->users[$identifier])) {
            return $this->users[$identifier];
        }

        throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
