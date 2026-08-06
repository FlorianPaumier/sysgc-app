<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function test_default_country_is_canada(): void
    {
        $address = new Address();

        self::assertSame('CA', $address->getCountry());
    }
}
