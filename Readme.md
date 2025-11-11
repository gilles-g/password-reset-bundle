# Password Expiration Bundle

A Symfony bundle that forces users to change their password after a certain period.

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

## User Entity Requirements

Your User entity must have a DateTime field that tracks when the password was last updated. The field name should match the `user_field` configuration option.

Example User entity:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
class User implements UserInterface
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
}
```

## How It Works

1. On every request, the bundle checks if the user is authenticated
2. If authenticated, it retrieves the password update date from the configured field
3. It calculates if the password has expired based on the `lifetime_days` configuration
4. If expired, the user is redirected to the configured `redirect_route`
5. Routes in the `excluded_routes` list are not checked (to prevent redirect loops)

## Example Password Change Controller

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

## License

MIT
