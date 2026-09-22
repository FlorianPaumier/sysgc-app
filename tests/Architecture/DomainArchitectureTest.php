<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainArchitectureTest extends TestCase
{
    private const CONTEXTS = [
        'Commercial',
        'Compliance',
        'Identity',
        'Project',
        'PublicSite',
        'Safety',
        'Shared',
        'Subscription',
        'Workforce',
    ];

    #[DataProvider('legacyLayerProvider')]
    public function test_legacy_mvc_layer_does_not_exist(string $layer): void
    {
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 2).'/src/'.$layer);
    }

    /** @return iterable<string, array{0: string}> */
    public static function legacyLayerProvider(): iterable
    {
        foreach (['Controller', 'DTO', 'Entity', 'Factory', 'Form', 'Message', 'MessageHandler', 'Repository', 'Security', 'Service'] as $layer) {
            yield $layer => [$layer];
        }
    }

    public function test_every_bounded_context_has_a_glossary_and_four_layers(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (self::CONTEXTS as $context) {
            $contextRoot = $root.'/src/'.$context;
            self::assertFileExists($contextRoot.'/CONTEXT.md', $context.' has no ubiquitous-language glossary.');

            foreach (['Domain', 'Application', 'Infrastructure', 'Presentation'] as $layer) {
                self::assertDirectoryExists($contextRoot.'/'.$layer, $context.' is missing its '.$layer.' layer.');
            }
        }
    }

    public function test_domain_never_depends_on_application_infrastructure_or_presentation(): void
    {
        $root = dirname(__DIR__, 2).'/src';

        foreach ($this->phpFilesNamedDomain($root) as $file) {
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);

            self::assertDoesNotMatchRegularExpression(
                '/^use App\\\\[^;]+\\\\(?:Application|Infrastructure|Presentation)\\\\/m',
                $contents,
                $file->getPathname().' points its domain layer outward.',
            );
        }
    }

    /** @return iterable<SplFileInfo> */
    private function phpFilesNamedDomain(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && 'php' === $file->getExtension() && str_contains($file->getPathname(), '/Domain/')) {
                yield $file;
            }
        }
    }
}
