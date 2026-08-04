<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base class for integration tests.
 * Extend WebTestCase for full stack testing with real database.
 */
abstract class IntegrationTestCase extends WebTestCase
{
    use ResetDatabase;
}
