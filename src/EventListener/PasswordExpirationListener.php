<?php

namespace GillesG\PasswordExpirationBundle\EventListener;

use GillesG\PasswordExpirationBundle\Service\PasswordExpirationChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

class PasswordExpirationListener extends AbstractListener implements FirewallListenerInterface
{
    public function __construct(
        private readonly int $lifetimeDays,
        private readonly string $redirectRoute,
        private readonly string $userField,
        private readonly array $excludedRoutes,
        private readonly string $firewallName,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PasswordExpirationChecker $expirationChecker
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Get the current route
        $currentRoute = $request->attributes->get('_route');

        // Check if the current route is excluded
        if (in_array($currentRoute, $this->excludedRoutes, true)) {
            return false;
        }

        // Check if the current route is the redirect route itself to avoid infinite loop
        if ($currentRoute === $this->redirectRoute) {
            return false;
        }

        // Return null to allow lazy evaluation when accessing token storage
        return null;
    }

    public function authenticate(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || !is_object($token->getUser())) {
            return;
        }

        $user = $token->getUser();

        if ($this->expirationChecker->isPasswordExpired($user, $this->userField, $this->lifetimeDays)) {
            $url = $this->urlGenerator->generate($this->redirectRoute);
            $event->setResponse(new RedirectResponse($url));
        }
    }

    public static function getPriority(): int
    {
        // Execute after authentication but before access control
        return -10;
    }
}
