<?php

namespace GillesG\PasswordExpirationBundle\Service;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

class PasswordHistoryChecker
{
    private PasswordHasherFactoryInterface $passwordHasherFactory;

    public function __construct(PasswordHasherFactoryInterface $passwordHasherFactory)
    {
        $this->passwordHasherFactory = $passwordHasherFactory;
    }

    /**
     * Check if a plain password has been used before by comparing against password history fingerprints
     *
     * @param object $user The user object
     * @param string $plainPassword The new plain password to check
     * @param string $historyField The field name containing the password history array
     * @return bool True if password was used before, false otherwise
     */
    public function isPasswordReused(object $user, string $plainPassword, string $historyField = 'passwordHistory'): bool
    {
        // Check if user has the configured history field
        $getter = 'get' . ucfirst($historyField);
        if (!method_exists($user, $getter)) {
            return false;
        }

        $passwordHistory = $user->$getter();

        if (!is_array($passwordHistory) || empty($passwordHistory)) {
            return false;
        }

        // Get the password hasher for this user
        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);

        // Check if the plain password matches any stored hash
        foreach ($passwordHistory as $historicalHash) {
            if ($hasher->verify($historicalHash, $plainPassword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a fingerprint (hash) of a plain password for storage
     * 
     * @param object $user The user object
     * @param string $plainPassword The plain password to fingerprint
     * @return string The password hash/fingerprint
     */
    public function createPasswordFingerprint(object $user, string $plainPassword): string
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);
        return $hasher->hash($plainPassword);
    }

    /**
     * Add a password fingerprint to user's history
     * 
     * @param object $user The user object
     * @param string $passwordHash The password hash to add to history
     * @param string $historyField The field name containing the password history array
     * @param int $maxHistory Maximum number of passwords to keep in history
     * @return void
     */
    public function addPasswordToHistory(
        object $user, 
        string $passwordHash, 
        string $historyField = 'passwordHistory',
        int $maxHistory = 5
    ): void {
        $getter = 'get' . ucfirst($historyField);
        $setter = 'set' . ucfirst($historyField);

        if (!method_exists($user, $getter) || !method_exists($user, $setter)) {
            return;
        }

        $history = $user->$getter() ?? [];
        
        // Add new password to the beginning of the array
        array_unshift($history, $passwordHash);
        
        // Keep only the last N passwords
        $history = array_slice($history, 0, $maxHistory);
        
        $user->$setter($history);
    }
}
