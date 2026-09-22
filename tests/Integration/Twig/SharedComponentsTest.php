<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class SharedComponentsTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $this->twig = $twig;
    }

    public function testPageHeaderRendersHierarchyContextAndPrimaryAction(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:PageHeader
                title="Déclaration CCQ"
                subtitle="Septembre 2026"
                context="Chantier de la Gare"
                :breadcrumbs="[{label: 'Chantiers', url: '/chantiers'}, {label: 'Déclaration CCQ'}]"
                actionLabel="Exporter le PDF"
                actionUrl="/declarations/12/export"
            />
            TWIG);

        self::assertStringContainsString('<header class="page-header">', $html);
        self::assertStringContainsString('aria-label="Fil d’Ariane"', $html);
        self::assertStringContainsString('href="/chantiers"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('Déclaration CCQ', $html);
        self::assertStringContainsString('Septembre 2026', $html);
        self::assertStringContainsString('Chantier de la Gare', $html);
        self::assertStringContainsString('href="/declarations/12/export"', $html);
        self::assertStringContainsString('Exporter le PDF', $html);
    }

    public function testStatusComponentsAlwaysExposeAVisibleTextLabel(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:StatusBadge status="warning" label="À surveiller" />
            <twig:StatusDot status="manual-review" label="Vérification manuelle requise" />
            TWIG);

        self::assertStringContainsString('class="status-badge status-badge--warning"', $html);
        self::assertStringContainsString('À surveiller', $html);
        self::assertStringContainsString('class="status-dot status-dot--manual-review"', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
        self::assertStringContainsString('Vérification manuelle requise', $html);
    }

    public function testTabBarMarksTheCurrentDestinationAndTargetsTurboFrames(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:TabBar
                label="Sections du chantier"
                activeTab="safety"
                :tabs="[
                    {id: 'overview', label: 'Aperçu', url: '/chantiers/7'},
                    {id: 'safety', label: 'Sécurité', url: '/chantiers/7/securite', frameId: 'project-content'}
                ]"
            />
            TWIG);

        self::assertStringContainsString('aria-label="Sections du chantier"', $html);
        self::assertStringContainsString('href="/chantiers/7/securite"', $html);
        self::assertStringContainsString('data-turbo-frame="project-content"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('tab-bar__link is-active', $html);
    }

    public function testMonthSelectorNamesThePeriodAndBothNavigationActions(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:MonthSelector month="9" year="2026" prevUrl="?month=8&year=2026" nextUrl="?month=10&year=2026" />
            TWIG);

        self::assertStringContainsString('aria-label="Choisir un mois"', $html);
        self::assertStringContainsString('aria-label="Mois précédent : août 2026"', $html);
        self::assertStringContainsString('<time datetime="2026-09">septembre 2026</time>', $html);
        self::assertStringContainsString('aria-label="Mois suivant : octobre 2026"', $html);
        self::assertStringContainsString('href="?month=10&amp;year=2026"', $html);
    }

    public function testToastUsesApoliteLiveRegionAndProvidesDismissal(): void
    {
        $html = $this->renderComponent('<twig:Toast type="success" message="Enregistré" duration="3000" />');

        self::assertStringContainsString('data-controller="toast"', $html);
        self::assertStringContainsString('data-toast-duration-value="3000"', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('data-action="toast#dismiss"', $html);
        self::assertStringContainsString('Enregistré', $html);
    }

    public function testModalProvidesAnAccessibleDialogAndExplicitCancelAction(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:Modal id="filing-confirmation" title="Confirmer la transmission" open="true">
                <p>Cette action conservera un instantané.</p>
            </twig:Modal>
            TWIG);

        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('id="filing-confirmation"', $html);
        self::assertStringContainsString('aria-labelledby="filing-confirmation-title"', $html);
        self::assertStringContainsString('aria-modal="true"', $html);
        self::assertStringContainsString('open>', $html);
        self::assertStringContainsString('data-controller="modal"', $html);
        self::assertStringContainsString('modal:open@window->modal#open', $html);
        self::assertStringContainsString('click->modal#backdropClose cancel->modal#close', $html);
        self::assertStringContainsString('data-action="modal#close"', $html);
        self::assertStringContainsString('Annuler', $html);
        self::assertStringContainsString('Cette action conservera un instantané.', $html);
    }

    public function testCollectionStatesShareAccessibleFrenchFeedbackContracts(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:LoadingState message="Chargement des déclarations" />
            <twig:ErrorState message="Impossible de charger les déclarations." retryLabel="Réessayer" retryUrl="/declarations" />
            <twig:EmptyState title="Aucune déclaration" message="Les déclarations apparaîtront ici." actionLabel="Créer" actionUrl="/declarations/nouvelle" />
            TWIG);

        self::assertStringContainsString('class="loading-state"', $html);
        self::assertStringContainsString('aria-busy="true"', $html);
        self::assertStringContainsString('Chargement des déclarations', $html);
        self::assertStringContainsString('class="error-state"', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('href="/declarations"', $html);
        self::assertStringContainsString('Réessayer', $html);
        self::assertStringContainsString('Les déclarations apparaîtront ici.', $html);
        self::assertStringContainsString('href="/declarations/nouvelle"', $html);
    }

    public function testNonUrgentAlertUsesAPoliteStatusAndSupportsRecoveryAction(): void
    {
        $html = $this->renderComponent('<twig:AlertBanner type="warning" message="Synchronisation en attente." actionLabel="Voir la file" actionUrl="/synchronisation" />');

        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('href="/synchronisation"', $html);
        self::assertStringContainsString('Voir la file', $html);
    }

    public function testDismissibleAlertWiresItsCloseControl(): void
    {
        $html = $this->renderComponent('<twig:AlertBanner type="error" message="Échec." dismissible="true" />');

        self::assertStringContainsString('data-controller="toast"', $html);
        self::assertStringContainsString('data-toast-duration-value="0"', $html);
        self::assertStringContainsString('data-action="toast#dismiss"', $html);
    }

    public function testScreenStateOrchestratesFrenchCrossScreenFeedback(): void
    {
        $html = $this->renderComponent(<<<'TWIG'
            <twig:ScreenState
                subscriptionState="read_only"
                :flashes="{success: ['Enregistré'], error: ['Échec'], info: ['Information'] }"
            />
            TWIG);

        self::assertStringContainsString('data-screen-state', $html);
        self::assertStringContainsString('data-network-state', $html);
        self::assertStringContainsString('Lecture seule', $html);
        self::assertStringContainsString('Renouveler', $html);
        self::assertStringContainsString('Enregistré', $html);
        self::assertStringContainsString('Échec', $html);
        self::assertStringContainsString('Information', $html);
    }

    private function renderComponent(string $source): string
    {
        return $this->twig->createTemplate($source)->render();
    }
}
