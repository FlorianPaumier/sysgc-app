<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Address;
use App\Entity\Organization;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class OrganizationTest extends TestCase
{
    public function test_name_is_mutable(): void
    {
        $organization = new Organization();

        self::assertNull($organization->getName());
        self::assertSame('Acme Construction', $organization->setName('Acme Construction')->getName());
    }

    public function test_rbq_license_is_mutable(): void
    {
        $organization = new Organization();

        self::assertNull($organization->getRbqLicense());
        self::assertSame('RBQ-1234-5678-90', $organization->setRbqLicense('RBQ-1234-5678-90')->getRbqLicense());
    }

    public function test_stripe_customer_id_is_nullable(): void
    {
        $organization = new Organization();

        self::assertNull($organization->getStripeCustomerId());
        self::assertSame('cus_abc', $organization->setStripeCustomerId('cus_abc')->getStripeCustomerId());
        self::assertNull($organization->setStripeCustomerId(null)->getStripeCustomerId());
    }

    public function test_address_is_mutable(): void
    {
        $organization = new Organization();
        $address = (new Address())->setStreet('123 rue example')->setCity('Montréal');

        self::assertSame($address, $organization->setAddress($address)->getAddress());
        self::assertSame('123 rue example', $organization->getAddress()->getStreet());
    }

    public function test_add_user_links_bidirectionally(): void
    {
        $organization = new Organization();
        $user = (new User())->setEmail('owner@example.com');

        $organization->addUser($user);

        self::assertTrue($organization->getUsers()->contains($user));
        self::assertSame($organization, $user->getOrganization());
    }

    public function test_add_user_is_idempotent(): void
    {
        $organization = new Organization();
        $user = (new User())->setEmail('owner@example.com');

        $organization->addUser($user);
        $organization->addUser($user);

        self::assertCount(1, $organization->getUsers());
    }

    public function test_remove_user_clears_back_reference(): void
    {
        $organization = new Organization();
        $user = (new User())->setEmail('owner@example.com');
        $organization->addUser($user);

        $organization->removeUser($user);

        self::assertFalse($organization->getUsers()->contains($user));
        self::assertNull($user->getOrganization());
    }
}
