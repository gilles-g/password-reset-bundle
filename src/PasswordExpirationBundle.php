<?php

namespace GillesG\PasswordExpirationBundle;

use GillesG\PasswordExpirationBundle\DependencyInjection\PasswordExpirationExtension;
use GillesG\PasswordExpirationBundle\DependencyInjection\Security\Factory\PasswordExpirationFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PasswordExpirationBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addAuthenticatorFactory(new PasswordExpirationFactory());
    }

    public function getContainerExtension(): ?PasswordExpirationExtension
    {
        if (null === $this->extension) {
            $this->extension = new PasswordExpirationExtension();
        }

        return $this->extension;
    }
}
