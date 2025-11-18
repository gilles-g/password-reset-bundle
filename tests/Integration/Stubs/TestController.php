<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration\Stubs;

use Symfony\Component\HttpFoundation\Response;

class TestController
{
    public function home(): Response
    {
        return new Response('Home Page');
    }

    public function passwordChange(): Response
    {
        return new Response('Password Change Page');
    }

    public function logout(): Response
    {
        return new Response('Logout Page');
    }
}
