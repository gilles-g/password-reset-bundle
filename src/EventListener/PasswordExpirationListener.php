<?php

namespace GillesG\PasswordExpirationBundle\EventListener;

use GillesG\PasswordExpirationBundle\Service\PasswordExpirationChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

class PasswordExpirationListener
{
    private int $lifetimeDays;
    private string $redirectRoute;
    private string $userField;
    private array $excludedRoutes;
    private string $firewallName;
    private TokenStorageInterface $tokenStorage;
    private UrlGeneratorInterface $urlGenerator;
    private FirewallMapInterface $firewallMap;
    private PasswordExpirationChecker $expirationChecker;

    public function __construct(
        int $lifetimeDays,
        string $redirectRoute,
        string $userField,
        array $excludedRoutes,
        string $firewallName,
        TokenStorageInterface $tokenStorage,
        UrlGeneratorInterface $urlGenerator,
        FirewallMapInterface $firewallMap,
        PasswordExpirationChecker $expirationChecker
    ) {
        $this->lifetimeDays = $lifetimeDays;
        $this->redirectRoute = $redirectRoute;
        $this->userField = $userField;
        $this->excludedRoutes = $excludedRoutes;
        $this->firewallName = $firewallName;
        $this->tokenStorage = $tokenStorage;
        $this->urlGenerator = $urlGenerator;
        $this->firewallMap = $firewallMap;
        $this->expirationChecker = $expirationChecker;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check if the current request is for the configured firewall
        $firewallConfig = $this->firewallMap->getFirewallConfig($request);
        if (null === $firewallConfig || $firewallConfig->getName() !== $this->firewallName) {
            return;
        }

        // Get the current route
        $currentRoute = $request->attributes->get('_route');

        // Check if the current route is excluded
        if (in_array($currentRoute, $this->excludedRoutes, true)) {
            return;
        }

        // Check if the current route is the redirect route itself to avoid infinite loop
        if ($currentRoute === $this->redirectRoute) {
            return;
        }

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
}
