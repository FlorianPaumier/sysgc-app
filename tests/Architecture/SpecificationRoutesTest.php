<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class SpecificationRoutesTest extends KernelTestCase
{
    /** @var array<string, array{path: string, methods: list<string>}> */
    private const ROUTES = [
        'app_dashboard' => ['path' => '/dashboard', 'methods' => ['GET']],
        'app_dashboard_alerts' => ['path' => '/dashboard/alerts', 'methods' => ['GET']],
        'app_project_index' => ['path' => '/chantiers', 'methods' => ['GET']],
        'app_project_new' => ['path' => '/chantiers/nouveau', 'methods' => ['GET', 'POST']],
        'app_project_show' => ['path' => '/chantiers/{id}', 'methods' => ['GET']],
        'app_project_edit' => ['path' => '/chantiers/{id}/modifier', 'methods' => ['GET', 'POST']],
        'app_project_archive' => ['path' => '/chantiers/{id}/archiver', 'methods' => ['POST']],
        'app_ccq_declaration' => ['path' => '/chantiers/{id}/ccq', 'methods' => ['GET']],
        'app_ccq_history' => ['path' => '/declarations', 'methods' => ['GET']],
        'app_ccq_show' => ['path' => '/declarations/{id}', 'methods' => ['GET']],
        'app_rbq_index' => ['path' => '/chantiers/{id}/rbq', 'methods' => ['GET']],
        'app_rbq_add' => ['path' => '/chantiers/{id}/rbq/ajouter', 'methods' => ['GET', 'POST']],
        'app_rbq_import' => ['path' => '/chantiers/{id}/rbq/import', 'methods' => ['GET', 'POST']],
        'app_rbq_verify_single' => ['path' => '/chantiers/{id}/rbq/{subId}/verifier', 'methods' => ['POST']],
        'app_rbq_verify_all' => ['path' => '/chantiers/{id}/rbq/tout-verifier', 'methods' => ['POST']],
        'app_worker_index' => ['path' => '/travailleurs', 'methods' => ['GET']],
        'app_worker_new' => ['path' => '/travailleurs/nouveau', 'methods' => ['GET', 'POST']],
        'app_worker_edit' => ['path' => '/travailleurs/{id}/modifier', 'methods' => ['GET', 'POST']],
        'app_change_order_index' => ['path' => '/chantiers/{id}/avenants', 'methods' => ['GET']],
        'app_change_order_new' => ['path' => '/chantiers/{id}/avenants/nouveau', 'methods' => ['GET', 'POST']],
        'app_change_order_show' => ['path' => '/chantiers/{id}/avenants/{changeOrderId}', 'methods' => ['GET']],
        'app_invoice_index' => ['path' => '/chantiers/{id}/factures', 'methods' => ['GET']],
        'app_invoice_new' => ['path' => '/chantiers/{id}/factures/nouvelle', 'methods' => ['GET', 'POST']],
        'app_invoice_pdf' => ['path' => '/chantiers/{id}/factures/{invoiceId}/pdf', 'methods' => ['GET']],
    ];

    public function test_current_feature_routes_match_the_specification(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        foreach (self::ROUTES as $name => $expected) {
            $route = $routes->get($name);
            self::assertNotNull($route, 'Missing specified route '.$name);
            self::assertSame($expected['path'], $route->getPath(), $name.' has the wrong public path.');
            self::assertSame($expected['methods'], $route->getMethods(), $name.' has the wrong HTTP methods.');
        }
    }
}
