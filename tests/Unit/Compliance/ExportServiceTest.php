<?php

declare(strict_types=1);

namespace App\Tests\Unit\Compliance;

use App\Compliance\Application\ExportService;
use App\Compliance\Domain\Model\CCQDeclaration;
use App\Identity\Domain\Model\Organization;
use App\Project\Domain\Model\Project;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ExportServiceTest extends TestCase
{
    public function test_generates_a_pdf_with_the_required_non_official_footer(): void
    {
        $organization = (new Organization())->setName('Construction Nord')->setRbqLicense('1234-5678-90');
        $project = new Project($organization, '100 Rue Principale', 'Client');
        $declaration = (new CCQDeclaration())
            ->setOrganization($organization)
            ->setProject($project)
            ->setPeriodMonth(6)
            ->setPeriodYear(2026)
            ->setStatus('draft')
            ->setDeclaration([]);
        $directory = sys_get_temp_dir().'/sysgc-ccq-'.bin2hex(random_bytes(6));
        $twig = new Environment(new ArrayLoader([
            'ccq/export.html.twig' => '{{ footer }} {{ organization.name }} {{ declaration.periodMonth }}/{{ declaration.periodYear }}',
        ]));

        $path = (new ExportService($twig, $directory))->generatePdf($declaration);

        self::assertFileExists($path);
        self::assertStringStartsWith('%PDF', (string) file_get_contents($path, false, null, 0, 4));
        unlink($path);
        rmdir($directory);
    }
}
