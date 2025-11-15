# Password Expiration Bundle

A Symfony bundle that forces users to change their password after a certain period and prevents password reuse using fingerprint technology.

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

## Architecture

The bundle provides a clean separation of concerns:

- **`PasswordExpirationChecker`** - Service that handles password expiration logic
- **`PasswordHistoryChecker`** - Service that prevents password reuse using fingerprint technology
- **`PasswordExpirationListener`** - Event listener that checks requests and triggers redirects
- **`PasswordExpirationFactory`** - Factory that registers the firewall configuration option
- **`PasswordExpirationExtension`** - Extension that loads the bundle services

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

## Resetting Git History

If you need to reset the git history of this repository while keeping all the code, see:

- **[HOW_TO_RESET_GIT_HISTORY.md](HOW_TO_RESET_GIT_HISTORY.md)** - Detailed step-by-step guide
- **[reset-git-history.sh](reset-git-history.sh)** - Automated script to reset history

⚠️ **Warning**: Resetting git history requires force push and should be coordinated with your team!

## License

MIT
