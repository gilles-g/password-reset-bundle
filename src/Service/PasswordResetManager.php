<?php

namespace GillesG\PasswordExpirationBundle\Service;

use GillesG\PasswordExpirationBundle\Model\PasswordResetToken;

class PasswordResetManager
{
    private array $tokens = [];

    public function __construct(
        private readonly int $tokenLifetimeSeconds = 3600
    ) {
    }

    /**
     * Generate a password reset token for a user.
     * Uses a selector/verifier pattern for secure token validation.
     */
    public function generateToken(object $user): PasswordResetToken
    {
        // Generate cryptographically secure random bytes
        $selector = bin2hex(random_bytes(16));
        $verifier = bin2hex(random_bytes(32));
        
        // Store the hashed verifier, not the plain one
        $hashedVerifier = hash('sha256', $verifier);
        
        $expiresAt = new \DateTime();
        $expiresAt->modify(sprintf('+%d seconds', $this->tokenLifetimeSeconds));
        
        $token = new PasswordResetToken(
            $verifier,
            $selector,
            $expiresAt,
            $user
        );
        
        // Store token with hashed verifier for security
        $this->tokens[$selector] = [
            'hashedVerifier' => $hashedVerifier,
            'expiresAt' => $expiresAt,
            'user' => $user
        ];
        
        return $token;
    }

    /**
     * Validate a password reset token.
     */
    public function validateToken(string $selector, string $verifier): ?PasswordResetToken
    {
        if (!isset($this->tokens[$selector])) {
            return null;
        }
        
        $tokenData = $this->tokens[$selector];
        
        // Check expiration
        if (new \DateTime() > $tokenData['expiresAt']) {
            unset($this->tokens[$selector]);
            return null;
        }
        
        // Verify the token using constant-time comparison
        $hashedVerifier = hash('sha256', $verifier);
        if (!hash_equals($tokenData['hashedVerifier'], $hashedVerifier)) {
            return null;
        }
        
        return new PasswordResetToken(
            $verifier,
            $selector,
            $tokenData['expiresAt'],
            $tokenData['user']
        );
    }

    /**
     * Invalidate a token after use.
     */
    public function invalidateToken(string $selector): void
    {
        unset($this->tokens[$selector]);
    }

    /**
     * Get the full token string (selector:verifier) for use in reset links.
     */
    public function getTokenString(PasswordResetToken $token): string
    {
        return $token->getSelector() . ':' . $token->getToken();
    }

    /**
     * Parse a token string into selector and verifier parts.
     */
    public function parseTokenString(string $tokenString): ?array
    {
        $parts = explode(':', $tokenString, 2);
        
        if (count($parts) !== 2) {
            return null;
        }
        
        return [
            'selector' => $parts[0],
            'verifier' => $parts[1]
        ];
    }
}
