<?php

namespace GillesG\PasswordExpirationBundle\Service;

class PasswordExpirationChecker
{
    /**
     * Check if a user's password has expired based on the last update date
     *
     * @param object $user The user object
     * @param string $userField The field name containing the password update date
     * @param int $lifetimeDays Number of days before password expiration
     * @return bool True if password is expired, false otherwise
     */
    public function isPasswordExpired(object $user, string $userField, int $lifetimeDays): bool
    {
        // Check if user has the configured field
        $getter = 'get' . ucfirst($userField);
        if (!method_exists($user, $getter)) {
            return false;
        }

        $passwordUpdatedAt = $user->$getter();

        if (!$passwordUpdatedAt instanceof \DateTimeInterface) {
            return false;
        }

        $now = new \DateTime();
        $expirationDate = clone $passwordUpdatedAt;
        $expirationDate->modify('+' . $lifetimeDays . ' days');

        return $now > $expirationDate;
    }
}
