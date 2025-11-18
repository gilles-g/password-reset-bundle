<?php

namespace GillesG\PasswordExpirationBundle\Tests\Integration;

use GillesG\PasswordExpirationBundle\Tests\Integration\Stubs\Kernel as KernelStub;
use GillesG\PasswordExpirationBundle\Tests\Integration\Stubs\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Functional tests that prove the PasswordExpirationListener works correctly
 * in a real Symfony application with actual HTTP requests.
 */
class PasswordExpirationListenerFunctionalTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $fs->remove(sys_get_temp_dir().'/PasswordExpirationBundle/');
        
        $this->client = static::createClient();
        $this->client->catchExceptions(false); // Don't catch exceptions during tests
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new KernelStub('test', true, $options['config'] ?? 'minimal');
    }

    /**
     * Test that authenticated user with expired password gets redirected
     */
    public function testExpiredPasswordRedirectsToChangePassword(): void
    {
        // Create a user with expired password (91 days old, lifetime is 90)
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-91 days'));
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Try to access home page
        $this->client->request('GET', '/');
        
        // Should be redirected to password change page
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect('/password/change'), 
            'User with expired password should be redirected to password change page');
    }

    /**
     * Test that authenticated user with valid password is not redirected
     */
    public function testValidPasswordAllowsAccess(): void
    {
        // Create a user with valid password (30 days old, lifetime is 90)
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-30 days'));
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Try to access home page
        $this->client->request('GET', '/');
        
        // Should NOT be redirected
        $response = $this->client->getResponse();
        $this->assertFalse($response->isRedirect(), 
            'User with valid password should not be redirected');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(),
            'User with valid password should access the page normally');
    }

    /**
     * Test that unauthenticated user is not affected by the listener
     */
    public function testUnauthenticatedUserNotAffected(): void
    {
        // Don't authenticate any user
        
        // Try to access home page
        $this->client->request('GET', '/');
        
        // Should work normally (no redirect to password change)
        $response = $this->client->getResponse();
        $this->assertFalse($response->isRedirect('/password/change'), 
            'Unauthenticated user should not be redirected to password change');
    }

    /**
     * Test that excluded routes are not checked
     */
    public function testExcludedRoutesNotChecked(): void
    {
        // Create a user with expired password
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-91 days'));
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Access an excluded route (logout)
        $this->client->request('GET', '/logout');
        
        // Should NOT be redirected even with expired password
        $response = $this->client->getResponse();
        $this->assertFalse($response->isRedirect('/password/change'), 
            'Excluded routes should not trigger password expiration check');
    }

    /**
     * Test that the redirect route itself is not checked (prevent infinite loop)
     */
    public function testRedirectRouteNotChecked(): void
    {
        // Create a user with expired password
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-91 days'));
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Access the password change route itself
        $this->client->request('GET', '/password/change');
        
        // Should NOT be redirected (no infinite loop)
        $response = $this->client->getResponse();
        $this->assertFalse($response->isRedirect(), 
            'Password change route should not redirect to itself');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(),
            'User should be able to access password change page');
    }

    /**
     * Test with user who has null passwordUpdatedAt (never set)
     */
    public function testNullPasswordDateNotConsideredExpired(): void
    {
        // Create a user with null password date
        $user = new User();
        $user->setPasswordUpdatedAt(null);
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Try to access home page
        $this->client->request('GET', '/');
        
        // Should NOT be redirected (null means no date set, which is allowed)
        $response = $this->client->getResponse();
        $this->assertFalse($response->isRedirect('/password/change'), 
            'User with null password date should not be redirected (field not yet set)');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(),
            'User with null password date should access the page normally');
    }

    /**
     * Test with user at exact expiration boundary (90 days)
     */
    public function testPasswordAtExactExpirationBoundary(): void
    {
        // Create a user with password exactly 90 days old
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-90 days'));
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Try to access home page
        $this->client->request('GET', '/');
        
        // Should be redirected (90 days is expired, not valid)
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect('/password/change'), 
            'User with password at exactly lifetime_days should be redirected');
    }

    /**
     * Test with fresh password (just updated today)
     */
    public function testFreshPasswordAllowsAccess(): void
    {
        // Create a user with fresh password
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime());
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Try to access home page
        $this->client->request('GET', '/');
        
        // Should NOT be redirected
        $response = $this->client->getResponse();
        $this->assertFalse($response->isRedirect(), 
            'User with fresh password should not be redirected');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(),
            'User with fresh password should access the page normally');
    }

    /**
     * Test that the listener works across multiple requests
     */
    public function testListenerWorksAcrossMultipleRequests(): void
    {
        // Create a user with expired password
        $user = new User();
        $user->setPasswordUpdatedAt(new \DateTime('-91 days'));
        
        // Authenticate the user
        $this->authenticateUser($user);
        
        // Make multiple requests
        $this->client->request('GET', '/');
        $this->assertTrue($this->client->getResponse()->isRedirect('/password/change'), 
            'First request should redirect');
        
        // Follow the redirect
        $this->client->followRedirect();
        $this->assertEquals('/password/change', $this->client->getRequest()->getPathInfo(),
            'Should be on password change page');
        
        // Try to access another page (simulate user trying to navigate away)
        $user2 = new User();
        $user2->setPasswordUpdatedAt(new \DateTime('-91 days'));
        $this->authenticateUser($user2);
        
        $this->client->request('GET', '/');
        $this->assertTrue($this->client->getResponse()->isRedirect('/password/change'), 
            'Subsequent request should also redirect');
    }

    /**
     * Helper method to authenticate a user in the test client
     */
    private function authenticateUser(User $user): void
    {
        // Register user with the test user provider
        $userProvider = static::getContainer()->get('test_user_provider');
        $userProvider->addUser($user);
        
        // Use Symfony's built-in loginUser method for simpler authentication
        $this->client->loginUser($user, 'main');
    }
}
