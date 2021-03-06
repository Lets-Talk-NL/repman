<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Functional\Controller;

use Buddy\Repman\Entity\User\OAuthToken;
use Buddy\Repman\Query\User\OrganizationQuery\DbalOrganizationQuery;
use Buddy\Repman\Tests\Functional\FunctionalTestCase;

final class UserControllerTest extends FunctionalTestCase
{
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->createAndLoginAdmin();
    }

    public function testProfileFormRendering(): void
    {
        $this->fixtures->createOrganization('buddy', $this->userId);
        $this->fixtures->createOauthToken($this->userId, OAuthToken::TYPE_GITHUB);
        $this->client->request('GET', $this->urlTo('user_profile'));

        self::assertTrue($this->client->getResponse()->isOk());
        self::assertStringContainsString('Profile', $this->lastResponseBody());
        self::assertStringContainsString('OAuth', $this->lastResponseBody());
        self::assertStringContainsString('Linked to <strong>Github</strong>', $this->lastResponseBody());
        self::assertStringContainsString('Unlink Github', $this->lastResponseBody());
        self::assertStringContainsString('Change password', $this->lastResponseBody());
        self::assertStringContainsString('Current password', $this->lastResponseBody());
        self::assertStringContainsString('New password', $this->lastResponseBody());
        self::assertStringContainsString('Repeat new password', $this->lastResponseBody());
        self::assertStringContainsString('Delete Account', $this->lastResponseBody());
    }

    public function testChangePassword(): void
    {
        $this->client->request('GET', $this->urlTo('user_profile'));

        $this->client->submitForm('changePassword', [
            'currentPassword' => 'password',
            'plainPassword[first]' => 'secret123',
            'plainPassword[second]' => 'secret123',
        ]);

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('user_profile')));

        $this->client->followRedirect();

        self::assertTrue($this->client->getResponse()->isOk());
        self::assertStringContainsString('Your password has been changed', $this->lastResponseBody());
    }

    public function testRemoveAccount(): void
    {
        $this->fixtures->createOrganization('buddy', $this->userId);
        $this->client->request('DELETE', $this->urlTo('user_remove'));

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('index')));

        self::assertFalse($this->container()->get(DbalOrganizationQuery::class)->getByAlias('buddy')->isEmpty());
    }

    public function testResendEmailVerification(): void
    {
        $this->client->request('POST', $this->urlTo('user_resend_verification'));
        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('user_profile')));

        $this->client->followRedirect();

        self::assertTrue($this->client->getResponse()->isOk());
        self::assertStringContainsString('Email sent successfully', $this->lastResponseBody());
    }

    public function testRemoveOAuthToken(): void
    {
        $this->fixtures->createOrganization('buddy', $this->userId);
        $this->fixtures->createOauthToken($this->userId, OAuthToken::TYPE_GITHUB);

        $this->client->request('DELETE', $this->urlTo('user_remove_oauth_token', ['type' => 'github']));

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('user_profile')));
        $this->client->followRedirect();
        self::assertStringContainsString('Github has been successfully unlinked.', $this->lastResponseBody());
    }

    public function testChangeEmailPreference(): void
    {
        $token = '3c5fb6d5-f8ff-49dd-a420-d8e77d979dc3';
        $this->createAndLoginAdmin('email', 'pass', $token);
        $this->fixtures->confirmUserEmail($token);

        $this->client->request('GET', $this->urlTo('user_profile'));
        $this->client->submitForm('changeEmailPreferences', [
            'emailScanResult' => false,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirect($this->urlTo('user_profile')));

        $this->client->followRedirect();

        self::assertTrue($this->client->getResponse()->isOk());
        self::assertStringContainsString('Email preferences have been changed', $this->lastResponseBody());
    }
}
