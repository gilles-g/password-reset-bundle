# Password Expiration Bundle

A Symfony bundle that forces users to change their password after a certain period, prevents password reuse using fingerprint technology, and provides password reset functionality with customizable email templates.

## Features

- 🔒 **Password Expiration**: Automatically enforce password changes after a configurable period
- 🔄 **Password History**: Prevent password reuse using secure fingerprint technology
- 📧 **Password Reset**: Token-based password reset with customizable email templates
- 🎨 **Customizable Templates**: Easily customize reset email content and design
- 🔐 **Secure Tokens**: Cryptographically secure token generation using selector/verifier pattern

## Requirements

- PHP 8.1 or higher
- Symfony 6.0 or higher (6.4 and 7.x supported)

## Installation

Install the bundle via Composer:

```bash
composer require gilles-g/password-expiration-bundle
```

Enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    GillesG\PasswordExpirationBundle\PasswordExpirationBundle::class => ['all' => true],
];
```

## Configuration

Add the `password_expiration` configuration to your firewall in `config/packages/security.yaml`:

```yaml
security:
    firewalls:
        main:
            provider: app_user_provider
            custom_authenticators:
                - App\Security\LoginFormAuthenticator

            password_expiration:
                lifetime_days: 90
                redirect_route: app_password_change
                user_field: passwordUpdatedAt
                excluded_routes: ['app_password_change', 'app_logout']
```

### Configuration Options

- **`lifetime_days`** (required, integer): Number of days before the password expires
- **`redirect_route`** (required, string): Route name where users will be redirected when their password has expired
- **`user_field`** (optional, string, default: `passwordUpdatedAt`): Name of the DateTime field in your User entity that stores when the password was last updated
- **`excluded_routes`** (optional, array, default: `[]`): Array of route names that should be excluded from the password expiration check (useful for logout, password change routes, etc.)

## Password Reset Feature

The bundle provides a complete password reset functionality with customizable email templates.

> **Note**: The default `PasswordResetManager` uses in-memory storage suitable for development and testing. For production use, you should extend it to persist tokens to a database (Doctrine), cache backend (Redis), or other persistent storage. See the "Production Token Storage" section below for details.

### Configuration

Add password reset configuration to `config/packages/password_expiration.yaml`:

```yaml
password_expiration:
    password_reset:
        enabled: true
        token_lifetime: 3600  # Token lifetime in seconds (default: 1 hour)
        email:
            from_email: 'noreply@example.com'
            from_name: 'My Application'
            subject: 'Reset Your Password'
            html_template: '@PasswordExpiration/emails/password_reset.html.twig'
            text_template: '@PasswordExpiration/emails/password_reset.txt.twig'
```

### Password Reset Configuration Options

- **`enabled`** (boolean, default: `false`): Enable or disable password reset functionality
- **`token_lifetime`** (integer, default: `3600`): Token lifetime in seconds (minimum: 60)
- **`email.from_email`** (string, required when enabled): Email address to send reset emails from
- **`email.from_name`** (string, default: `'Password Reset'`): Name displayed as the sender
- **`email.subject`** (string, default: `'Password Reset Request'`): Email subject line
- **`email.html_template`** (string): Path to custom HTML email template
- **`email.text_template`** (string): Path to custom text email template

### Usage Example

```php
<?php

namespace App\Controller;

use GillesG\PasswordExpirationBundle\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetController extends AbstractController
{
    #[Route('/password/reset/request', name: 'app_password_reset_request')]
    public function requestReset(
        Request $request,
        PasswordResetService $resetService
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            // Find user by email (adapt to your user repository)
            $user = $this->getDoctrine()
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);
            
            if ($user) {
                // Generate reset URL
                $resetUrl = $this->generateUrl(
                    'app_password_reset_confirm',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                
                // Send reset email
                $resetService->requestPasswordReset($user, $resetUrl);
            }
            
            // Always show success message (security best practice)
            $this->addFlash('success', 'If an account with that email exists, a password reset link has been sent.');
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('security/reset_request.html.twig');
    }
    
    #[Route('/password/reset/confirm', name: 'app_password_reset_confirm')]
    public function confirmReset(
        Request $request,
        PasswordResetService $resetService,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $token = $request->query->get('token');
        
        if ($request->isMethod('POST')) {
            // Validate token
            $resetToken = $resetService->validateResetToken($token);
            
            if (!$resetToken) {
                $this->addFlash('error', 'Invalid or expired reset token.');
                return $this->redirectToRoute('app_password_reset_request');
            }
            
            $user = $resetToken->getUser();
            $newPassword = $request->request->get('password');
            
            // Update password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setPasswordUpdatedAt(new \DateTime());
            
            // Invalidate the token
            $resetService->completePasswordReset($resetToken);
            
            // Persist changes
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();
            
            $this->addFlash('success', 'Your password has been reset successfully!');
            return $this->redirectToRoute('app_login');
        }
        
        // Validate token for GET request
        $resetToken = $resetService->validateResetToken($token);
        if (!$resetToken) {
            $this->addFlash('error', 'Invalid or expired reset token.');
            return $this->redirectToRoute('app_password_reset_request');
        }
        
        return $this->render('security/reset_confirm.html.twig', [
            'token' => $token
        ]);
    }
}
```

### Customizing Email Templates

The bundle provides default email templates, but you can easily customize them:

1. **Create your custom templates** in your application's `templates/` directory:

```twig
{# templates/emails/my_custom_reset.html.twig #}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* Your custom styles */
    </style>
</head>
<body>
    <h1>Custom Password Reset</h1>
    <p>Hello {{ user.email }},</p>
    <p>Click here to reset your password:</p>
    <a href="{{ resetUrl }}">Reset Password</a>
    <p>This link expires at {{ expiresAt|date('Y-m-d H:i:s') }}.</p>
</body>
</html>
```

```twig
{# templates/emails/my_custom_reset.txt.twig #}
Custom Password Reset

Hello {{ user.email }},

Click the link below to reset your password:
{{ resetUrl }}

This link expires at {{ expiresAt|date('Y-m-d H:i:s') }}.
```

2. **Update your configuration** to use the custom templates:

```yaml
password_expiration:
    password_reset:
        enabled: true
        email:
            from_email: 'noreply@example.com'
            html_template: 'emails/my_custom_reset.html.twig'
            text_template: 'emails/my_custom_reset.txt.twig'
```

### Available Template Variables

The following variables are available in your email templates:

- **`user`**: The user object requesting the password reset
- **`resetUrl`**: The complete password reset URL with token
- **`expiresAt`**: DateTime object indicating when the token expires

### Production Token Storage

The default `PasswordResetManager` uses in-memory storage which is **not suitable for production**. For production environments, you should create a custom implementation that persists tokens to a database or cache backend.

**Example with Doctrine:**

```php
<?php

namespace App\Service;

use App\Entity\PasswordResetToken as PasswordResetTokenEntity;
use Doctrine\ORM\EntityManagerInterface;
use GillesG\PasswordExpirationBundle\Model\PasswordResetToken;
use GillesG\PasswordExpirationBundle\Service\PasswordResetManager as BasePasswordResetManager;

class DoctrinePasswordResetManager extends BasePasswordResetManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        int $tokenLifetimeSeconds = 3600
    ) {
        parent::__construct($tokenLifetimeSeconds);
    }

    public function generateToken(object $user): PasswordResetToken
    {
        $token = parent::generateToken($user);
        
        // Persist to database
        $entity = new PasswordResetTokenEntity();
        $entity->setSelector($token->getSelector());
        $entity->setHashedVerifier(hash('sha256', $token->getToken()));
        $entity->setExpiresAt($token->getExpiresAt());
        $entity->setUser($user);
        
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        
        return $token;
    }

    public function validateToken(string $selector, string $verifier): ?PasswordResetToken
    {
        $entity = $this->entityManager
            ->getRepository(PasswordResetTokenEntity::class)
            ->findOneBy(['selector' => $selector]);
        
        if (!$entity || $entity->getExpiresAt() < new \DateTime()) {
            return null;
        }
        
        $hashedVerifier = hash('sha256', $verifier);
        if (!hash_equals($entity->getHashedVerifier(), $hashedVerifier)) {
            return null;
        }
        
        return new PasswordResetToken(
            $verifier,
            $selector,
            $entity->getExpiresAt(),
            $entity->getUser()
        );
    }

    public function invalidateToken(string $selector): void
    {
        $entity = $this->entityManager
            ->getRepository(PasswordResetTokenEntity::class)
            ->findOneBy(['selector' => $selector]);
        
        if ($entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
```

**Register your custom manager:**

```yaml
# config/services.yaml
services:
    App\Service\DoctrinePasswordResetManager:
        arguments:
            $tokenLifetimeSeconds: 3600
    
    # Override the default manager
    GillesG\PasswordExpirationBundle\Service\PasswordResetManager:
        alias: App\Service\DoctrinePasswordResetManager
```

## Architecture

The bundle provides a clean separation of concerns:

### Password Expiration Services

- **`PasswordExpirationChecker`** - Service that handles password expiration logic
- **`PasswordHistoryChecker`** - Service that prevents password reuse using fingerprint technology
- **`PasswordExpirationListener`** - Event listener that checks requests and triggers redirects
- **`PasswordExpirationFactory`** - Factory that registers the firewall configuration option
- **`PasswordExpirationExtension`** - Extension that loads the bundle services

### Password Reset Services

- **`PasswordResetManager`** - Handles secure token generation, validation, and lifecycle management
- **`PasswordResetService`** - Orchestrates the password reset flow and email sending
- **`PasswordResetToken`** - Value object representing a password reset token with selector/verifier pattern

## User Entity Requirements

Your User entity must have a DateTime field that tracks when the password was last updated. The field name should match the `user_field` configuration option.

**Optional:** To enable password history tracking (preventing password reuse), add a `passwordHistory` field as an array to store password fingerprints.

Example User entity:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $passwordUpdatedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $passwordHistory = [];

    // ... other fields and methods

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
        return $this->passwordHistory ?? [];
    }

    public function setPasswordHistory(array $passwordHistory): self
    {
        $this->passwordHistory = $passwordHistory;
        return $this;
    }
}
```

## How It Works

### Password Expiration

1. On every request, the bundle checks if the user is authenticated
2. If authenticated, it retrieves the password update date from the configured field
3. It calculates if the password has expired based on the `lifetime_days` configuration
4. If expired, the user is redirected to the configured `redirect_route`
5. Routes in the `excluded_routes` list are not checked (to prevent redirect loops)

### Password Reuse Prevention (Fingerprint Technology)

The bundle provides a `PasswordHistoryChecker` service that prevents users from reusing previous passwords:

- **Fingerprint Storage**: Passwords are stored as secure hashes (fingerprints) using Symfony's password hasher
- **History Tracking**: Maintains a configurable history of previous password hashes
- **Reuse Detection**: Checks new passwords against the history to prevent reuse
- **No Plain Text**: Never stores actual passwords, only cryptographic fingerprints

## Example Password Change Controller

### Basic Example

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class PasswordChangeController extends AbstractController
{
    #[Route('/password/change', name: 'app_password_change')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();
        
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            
            // Hash and update password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            // Update the password timestamp
            $user->setPasswordUpdatedAt(new \DateTime());
            
            // Persist to database
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();
            
            $this->addFlash('success', 'Password updated successfully!');
            return $this->redirectToRoute('app_home');
        }
        
        return $this->render('password/change.html.twig');
    }
}
```

### With Password History (Preventing Reuse)

```php
<?php

namespace App\Controller;

use GillesG\PasswordExpirationBundle\Service\PasswordHistoryChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class PasswordChangeController extends AbstractController
{
    #[Route('/password/change', name: 'app_password_change')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        PasswordHistoryChecker $historyChecker
    ): Response {
        $user = $this->getUser();
        
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            
            // Check if password was used before
            if ($historyChecker->isPasswordReused($user, $newPassword)) {
                $this->addFlash('error', 'You cannot reuse a previous password. Please choose a different password.');
                return $this->render('password/change.html.twig');
            }
            
            // Hash and update password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            // Add current password to history (before setting new one)
            // Keep last 5 passwords in history
            $historyChecker->addPasswordToHistory($user, $hashedPassword, 'passwordHistory', 5);
            
            // Update the password timestamp
            $user->setPasswordUpdatedAt(new \DateTime());
            
            // Persist to database
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();
            
            $this->addFlash('success', 'Password updated successfully!');
            return $this->redirectToRoute('app_home');
        }
        
        return $this->render('password/change.html.twig');
    }
}
```

## Security

### Password Reset Token Security

The password reset feature uses a **selector/verifier pattern** for maximum security:

1. **Cryptographically Secure Generation**: Tokens are generated using `random_bytes()` for cryptographic randomness
2. **Selector/Verifier Split**: Each token consists of two parts:
   - **Selector**: Used to look up the token (stored in plain text)
   - **Verifier**: Used to validate the token (stored as SHA-256 hash)
3. **Constant-Time Comparison**: Token validation uses `hash_equals()` to prevent timing attacks
4. **Single Use**: Tokens are automatically invalidated after successful use
5. **Time-Limited**: Tokens expire after a configurable period (default: 1 hour)

This approach is inspired by security best practices and prevents:
- Token enumeration attacks
- Timing attacks
- Database leakage vulnerabilities
- Token reuse

### Best Practices

When implementing password reset in your application:

1. **Never Confirm Email Existence**: Always show a success message whether or not the email exists
2. **Rate Limiting**: Implement rate limiting on the reset request endpoint
3. **Audit Logging**: Log password reset attempts for security monitoring
4. **HTTPS Only**: Always use HTTPS in production
5. **Secure Token Transmission**: Tokens should only be transmitted via secure channels

## Testing

The bundle includes comprehensive tests:

```bash
composer install
vendor/bin/phpunit
```

### Test Structure

- **Integration Tests** - Boot real Symfony kernel with different configurations
- **Unit Tests** - Test individual components like `PasswordExpirationChecker` and `PasswordHistoryChecker`
- **DependencyInjection Tests** - Verify proper container configuration

See the `tests/` directory for examples of how to test the bundle in your application.

## PasswordHistoryChecker API Reference

The `PasswordHistoryChecker` service provides the following methods:

### `isPasswordReused(object $user, string $plainPassword, string $historyField = 'passwordHistory'): bool`

Checks if a plain password has been used before by comparing against password history fingerprints.

**Parameters:**
- `$user`: The user object
- `$plainPassword`: The new plain password to check
- `$historyField`: (optional) The field name containing the password history array (default: 'passwordHistory')

**Returns:** `true` if password was used before, `false` otherwise

### `createPasswordFingerprint(object $user, string $plainPassword): string`

Creates a fingerprint (hash) of a plain password for storage.

**Parameters:**
- `$user`: The user object
- `$plainPassword`: The plain password to fingerprint

**Returns:** The password hash/fingerprint

### `addPasswordToHistory(object $user, string $passwordHash, string $historyField = 'passwordHistory', int $maxHistory = 5): void`

Adds a password fingerprint to user's history with automatic rotation.

**Parameters:**
- `$user`: The user object
- `$passwordHash`: The password hash to add to history
- `$historyField`: (optional) The field name containing the password history array (default: 'passwordHistory')
- `$maxHistory`: (optional) Maximum number of passwords to keep in history (default: 5)

## PasswordResetService API Reference

The `PasswordResetService` service provides the following methods:

### `requestPasswordReset(object $user, string $resetUrl): PasswordResetToken`

Initiates a password reset request for a user by generating a token and sending an email.

**Parameters:**
- `$user`: The user object requesting password reset
- `$resetUrl`: The base URL for the password reset page (token will be appended as query parameter)

**Returns:** `PasswordResetToken` object

**Example:**
```php
$resetUrl = $this->generateUrl('app_password_reset_confirm', [], UrlGeneratorInterface::ABSOLUTE_URL);
$token = $resetService->requestPasswordReset($user, $resetUrl);
```

### `validateResetToken(string $tokenString): ?PasswordResetToken`

Validates a password reset token string.

**Parameters:**
- `$tokenString`: The complete token string in format "selector:verifier"

**Returns:** `PasswordResetToken` object if valid, `null` if invalid or expired

**Example:**
```php
$tokenString = $request->query->get('token');
$resetToken = $resetService->validateResetToken($tokenString);

if ($resetToken === null) {
    // Token is invalid or expired
}
```

### `completePasswordReset(PasswordResetToken $token): void`

Completes the password reset process by invalidating the token.

**Parameters:**
- `$token`: The validated PasswordResetToken object

**Example:**
```php
// After successfully updating the password
$resetService->completePasswordReset($resetToken);
```

## PasswordResetManager API Reference

The `PasswordResetManager` service provides low-level token management. Most applications should use `PasswordResetService` instead.

### `generateToken(object $user): PasswordResetToken`

Generates a new password reset token for a user.

**Parameters:**
- `$user`: The user object

**Returns:** `PasswordResetToken` object

### `validateToken(string $selector, string $verifier): ?PasswordResetToken`

Validates a token using its selector and verifier components.

**Parameters:**
- `$selector`: The token selector
- `$verifier`: The token verifier

**Returns:** `PasswordResetToken` object if valid, `null` otherwise

### `getTokenString(PasswordResetToken $token): string`

Converts a token object to a string suitable for URLs.

**Parameters:**
- `$token`: The PasswordResetToken object

**Returns:** Token string in format "selector:verifier"

### `parseTokenString(string $tokenString): ?array`

Parses a token string into its components.

**Parameters:**
- `$tokenString`: The token string to parse

**Returns:** Array with 'selector' and 'verifier' keys, or `null` if invalid

## License

MIT
