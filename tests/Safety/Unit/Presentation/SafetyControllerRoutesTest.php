<?php

declare(strict_types=1);

namespace App\Tests\Safety\Unit\Presentation;

use App\Safety\Presentation\Http\SafetyController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class SafetyControllerRoutesTest extends TestCase
{
    public function test_exposes_the_calendar_and_daily_safety_log_routes(): void
    {
        $controller = new \ReflectionClass(SafetyController::class);
        $index = $controller->getMethod('index')->getAttributes(Route::class)[0]->newInstance();
        $daily = $controller->getMethod('daily')->getAttributes(Route::class)[0]->newInstance();

        self::assertSame('/{id}/securite', $index->path);
        self::assertSame(['GET'], $index->methods);
        self::assertSame('/{id}/securite/{date}', $daily->path);
        self::assertSame(['GET'], $daily->methods);
    }

    public function test_calendar_accepts_a_month_and_year_for_navigation(): void
    {
        $controller = new \ReflectionClass(SafetyController::class);
        $index = $controller->getMethod('index');

        self::assertNotNull(array_find($index->getParameters(), static fn (\ReflectionParameter $parameter): bool => 'month' === $parameter->getName()));
        self::assertNotNull(array_find($index->getParameters(), static fn (\ReflectionParameter $parameter): bool => 'year' === $parameter->getName()));
    }
}
