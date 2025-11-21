<?php

namespace GillesG\PasswordExpirationBundle\Tests\Service;

use GillesG\PasswordExpirationBundle\Service\PasswordResetManager;
use GillesG\PasswordExpirationBundle\Service\PasswordResetService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class PasswordResetServiceTest extends TestCase
{
    private PasswordResetManager $resetManager;
    private MailerInterface $mailer;
    private Environment $twig;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->resetManager = new PasswordResetManager(3600);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->service = new PasswordResetService(
            $this->resetManager,
            $this->mailer,
            $this->twig,
            'noreply@example.com',
            'Test App',
            '@PasswordExpiration/emails/password_reset.html.twig',
            '@PasswordExpiration/emails/password_reset.txt.twig',
            'Reset Your Password'
        );
    }

    public function testRequestPasswordReset(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $this->twig->expects($this->exactly(2))
            ->method('render')
            ->willReturn('rendered template');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return $email instanceof Email
                    && $email->getTo()[0]->getAddress() === 'test@example.com'
                    && str_contains($email->getFrom()[0]->toString(), 'noreply@example.com')
                    && $email->getSubject() === 'Reset Your Password';
            }));

        $token = $this->service->requestPasswordReset($user, 'https://example.com/reset');

        $this->assertNotNull($token);
        $this->assertSame($user, $token->getUser());
    }

    public function testRequestPasswordResetWithGetEmailMethod(): void
    {
        $user = new class {
            public function getEmail(): string
            {
                return 'user@example.com';
            }
        };

        $this->twig->method('render')->willReturn('rendered template');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return $email instanceof Email
                    && $email->getTo()[0]->getAddress() === 'user@example.com';
            }));

        $this->service->requestPasswordReset($user, 'https://example.com/reset');
    }

    public function testRequestPasswordResetWithoutEmail(): void
    {
        $user = new \stdClass();
        // No email property

        $this->twig->method('render')->willReturn('rendered template');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine user email address');

        $this->service->requestPasswordReset($user, 'https://example.com/reset');
    }

    public function testValidateResetToken(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $this->twig->method('render')->willReturn('rendered template');
        $this->mailer->method('send');

        $token = $this->service->requestPasswordReset($user, 'https://example.com/reset');
        $tokenString = $this->resetManager->getTokenString($token);

        $validatedToken = $this->service->validateResetToken($tokenString);

        $this->assertNotNull($validatedToken);
        $this->assertSame($user, $validatedToken->getUser());
    }

    public function testValidateInvalidResetToken(): void
    {
        $result = $this->service->validateResetToken('invalid:token');

        $this->assertNull($result);
    }

    public function testCompletePasswordReset(): void
    {
        $user = new \stdClass();
        $user->email = 'test@example.com';

        $this->twig->method('render')->willReturn('rendered template');
        $this->mailer->method('send');

        $token = $this->service->requestPasswordReset($user, 'https://example.com/reset');
        $tokenString = $this->resetManager->getTokenString($token);

        $this->service->completePasswordReset($token);

        // Token should now be invalid
        $result = $this->service->validateResetToken($tokenString);
        $this->assertNull($result);
    }
}
