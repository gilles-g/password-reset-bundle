<?php

namespace GillesG\PasswordExpirationBundle\Service;

use GillesG\PasswordExpirationBundle\Model\PasswordResetToken;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class PasswordResetService
{
    public function __construct(
        private readonly PasswordResetManager $resetManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $fromEmail,
        private readonly string $fromName = 'Password Reset',
        private readonly string $htmlTemplate = '@PasswordExpiration/emails/password_reset.html.twig',
        private readonly string $textTemplate = '@PasswordExpiration/emails/password_reset.txt.twig',
        private readonly string $subject = 'Password Reset Request'
    ) {
    }

    /**
     * Request a password reset for a user.
     * Generates a token and sends an email with the reset link.
     */
    public function requestPasswordReset(object $user, string $resetUrl): PasswordResetToken
    {
        $token = $this->resetManager->generateToken($user);
        $tokenString = $this->resetManager->getTokenString($token);
        
        // Build the complete reset URL
        $fullResetUrl = $resetUrl . '?token=' . urlencode($tokenString);
        
        // Send the email
        $this->sendResetEmail($user, $fullResetUrl, $token);
        
        return $token;
    }

    /**
     * Validate a password reset token.
     */
    public function validateResetToken(string $tokenString): ?PasswordResetToken
    {
        $parts = $this->resetManager->parseTokenString($tokenString);
        
        if ($parts === null) {
            return null;
        }
        
        return $this->resetManager->validateToken($parts['selector'], $parts['verifier']);
    }

    /**
     * Complete the password reset process.
     */
    public function completePasswordReset(PasswordResetToken $token): void
    {
        $this->resetManager->invalidateToken($token->getSelector());
    }

    /**
     * Send the password reset email to the user.
     */
    private function sendResetEmail(object $user, string $resetUrl, PasswordResetToken $token): void
    {
        // Get user email - try common field names
        $userEmail = $this->getUserEmail($user);
        
        if (!$userEmail) {
            throw new \RuntimeException('Unable to determine user email address. Ensure your User entity has an email field or getEmail() method.');
        }
        
        // Render email templates
        $htmlBody = $this->twig->render($this->htmlTemplate, [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'expiresAt' => $token->getExpiresAt(),
        ]);
        
        $textBody = $this->twig->render($this->textTemplate, [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'expiresAt' => $token->getExpiresAt(),
        ]);
        
        // Create and send email
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($userEmail)
            ->subject($this->subject)
            ->html($htmlBody)
            ->text($textBody);
        
        $this->mailer->send($email);
    }

    /**
     * Extract email address from user object.
     */
    private function getUserEmail(object $user): ?string
    {
        // Try getEmail() method
        if (method_exists($user, 'getEmail')) {
            return $user->getEmail();
        }
        
        // Try email property
        if (property_exists($user, 'email')) {
            return $user->email;
        }
        
        // Try getUserIdentifier() as fallback (if it's an email)
        if (method_exists($user, 'getUserIdentifier')) {
            $identifier = $user->getUserIdentifier();
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                return $identifier;
            }
        }
        
        return null;
    }
}
