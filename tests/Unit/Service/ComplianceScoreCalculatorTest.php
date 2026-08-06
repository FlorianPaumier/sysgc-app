<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CCQDeclaration;
use App\Entity\Enum\InvoiceStatus;
use App\Entity\Invoice;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\SafetyLog;
use App\Entity\Subcontractor;
use App\Service\ComplianceScoreCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComplianceScoreCalculatorTest extends TestCase
{
    private ComplianceScoreCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ComplianceScoreCalculator;
    }

    // ── Score calculation ──────────────────────────────────────

    #[Test]
    public function allComponentsPresentReturns100(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            ccqCurrentMonth: true,   // 35
            ccqLastMonth: true,      // 15
            safetyLogToday: true,     // 20
            safetyLogsAllWeek: true,  // 10
            allSubsVerified: true,    // 15
            noOverdueInvoices: true,  // 5
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(100, $score);
    }

    #[Test]
    public function nothingPresentReturns0(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createEmptyProject();

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(0, $score);
    }

    #[Test]
    public function onlyCcqCurrentMonthReturns35(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            ccqCurrentMonth: true,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(35, $score);
    }

    #[Test]
    public function onlyCcqLastMonthReturns15(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            ccqLastMonth: true,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(15, $score);
    }

    #[Test]
    public function onlySafetyLogTodayReturns20(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            safetyLogToday: true,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(20, $score);
    }

    #[Test]
    public function safetyLogsAllWeekdaysReturns10(): void
    {
        $now = new \DateTimeImmutable('2026-08-15'); // Friday — week runs Mon 11 to Fri 15
        $project = $this->createProject(
            safetyLogsAllWeek: true,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(10, $score);
    }

    #[Test]
    public function onlySomeWeekdayLogsReturns0(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createEmptyProject();

        // Only Monday and Wednesday have logs
        $this->addSafetyLog($project, new \DateTimeImmutable('2026-08-11')); // Mon
        $this->addSafetyLog($project, new \DateTimeImmutable('2026-08-13')); // Wed

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(0, $score); // not all 5 weekdays covered
    }

    #[Test]
    public function allSubsVerifiedReturns15(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            allSubsVerified: true,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(15, $score);
    }

    #[Test]
    public function oneSubNotVerifiedReturns0ForSubComponent(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createEmptyProject();
        $this->addVerifiedSubcontractor($project);
        $this->addUnverifiedSubcontractor($project);

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(0, $score);
    }

    #[Test]
    public function noSubcontractorsCountsAsAllVerified(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createEmptyProject();
        $project->getSubcontractors()->clear();
        // Keep the overdue invoice (baseline) so only 15 from subs + 0 from invoices = 15

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(15, $score);
    }

    #[Test]
    public function noOverdueInvoicesReturns5(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            noOverdueInvoices: true,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(5, $score);
    }

    #[Test]
    public function oneOverdueInvoiceReturns0ForInvoiceComponent(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createEmptyProject();
        $this->addInvoice($project, InvoiceStatus::PAID);
        $this->addInvoice($project, InvoiceStatus::OVERDUE);

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(0, $score); // only overdue kills the 5 pts, nothing else present
    }

    #[Test]
    public function noInvoicesCountsAsNoOverdue(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createEmptyProject();
        $project->getInvoices()->clear();
        // Keep the unverified sub (baseline) so only 5 from invoices + 0 from subs = 5

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(5, $score);
    }

    #[Test]
    public function partialCombinationReturnsCorrectSum(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $project = $this->createProject(
            ccqCurrentMonth: true,   // 35
            ccqLastMonth: false,
            safetyLogToday: true,     // 20
            safetyLogsAllWeek: false,
            allSubsVerified: true,    // 15
            noOverdueInvoices: false,
            now: $now,
        );

        $score = $this->calculator->calculate($project, $now);

        $this->assertSame(70, $score); // 35 + 20 + 15 = 70
    }

    // ── Current month => default $now ──────────────────────────

    #[Test]
    public function usesCurrentDateWhenNowNotProvided(): void
    {
        $project = $this->createEmptyProject();

        $score = $this->calculator->calculate($project);

        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    // ── Badge thresholds ───────────────────────────────────────

    #[Test]
    #[DataProvider('badgeProvider')]
    public function badgeThresholds(int $score, string $expectedLabel, string $expectedColor): void
    {
        $badge = $this->calculator->getBadge($score);

        $this->assertSame($expectedLabel, $badge['label']);
        $this->assertSame($expectedColor, $badge['color']);
    }

    public static function badgeProvider(): iterable
    {
        yield 'score 100 => conforme green' => [100, 'Conforme', 'green'];
        yield 'score 80 => conforme green' => [80, 'Conforme', 'green'];
        yield 'score 79 => à surveiller yellow' => [79, 'À surveiller', 'yellow'];
        yield 'score 50 => à surveiller yellow' => [50, 'À surveiller', 'yellow'];
        yield 'score 49 => non conforme red' => [49, 'Non conforme', 'red'];
        yield 'score 0 => non conforme red' => [0, 'Non conforme', 'red'];
    }

    // ── Alerts ─────────────────────────────────────────────────

    #[Test]
    public function getAlertsReturnsOkWhenEverythingGreen(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $org = $this->createOrganization();
        $project = $this->createProject(
            ccqCurrentMonth: true,
            safetyLogToday: true,
            allSubsVerified: true,
            now: $now,
        );
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);

        $this->assertCount(1, $alerts);
        $this->assertSame('ok', $alerts[0]['level']);
    }

    #[Test]
    public function getAlertsCriticalWhenCcqDeadlineOverdue(): void
    {
        $now = new \DateTimeImmutable('2026-08-31'); // end of month = deadline
        $org = $this->createOrganization();
        $project = $this->createEmptyProject(); // no CCQ declaration
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);

        $this->assertNotEmpty($alerts);
        $critical = $alerts[0];
        $this->assertSame('critical', $critical['level']);
        $this->assertStringContainsString('CCQ', $critical['message']);
    }

    #[Test]
    public function getAlertsWarningWhenCcqDeadlineWithin7Days(): void
    {
        $now = new \DateTimeImmutable('2026-07-27'); // July has 31 days, 4 days to deadline
        $org = $this->createOrganization();
        $project = $this->createEmptyProject();
        $project->getSubcontractors()->clear(); // avoid critical sub alert masking the warning
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);

        $this->assertNotEmpty($alerts);
        $this->assertSame('warning', $alerts[0]['level']);
    }

    #[Test]
    public function getAlertsWarningWhenSafetyLogMissingToday(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $org = $this->createOrganization();
        $project = $this->createProject(
            ccqCurrentMonth: true, // green on CCQ
            safetyLogToday: false,  // missing
            allSubsVerified: true,
            now: $now,
        );
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);

        $hasSafetyWarning = false;
        foreach ($alerts as $alert) {
            if ($alert['level'] === 'warning' && str_contains($alert['message'], 'sécurité')) {
                $hasSafetyWarning = true;
            }
        }
        $this->assertTrue($hasSafetyWarning, 'Expected a safety-log warning alert');
    }

    #[Test]
    public function getAlertsCriticalWhenSubcontractorNotVerified(): void
    {
        $now = new \DateTimeImmutable('2026-08-15');
        $org = $this->createOrganization();
        $project = $this->createEmptyProject();
        $this->addUnverifiedSubcontractor($project);
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);

        $this->assertNotEmpty($alerts);
        $this->assertSame('critical', $alerts[0]['level']);
    }

    #[Test]
    public function getAlertsHighestPriorityFirst(): void
    {
        $now = new \DateTimeImmutable('2026-08-31');
        $org = $this->createOrganization();
        $project = $this->createEmptyProject();
        $this->addUnverifiedSubcontractor($project); // critical too
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);
        // Both CCQ overdue and unverified sub are critical — first should be critical
        $this->assertSame('critical', $alerts[0]['level']);
    }

    #[Test]
    public function getAlertsIncludesProjectLink(): void
    {
        $now = new \DateTimeImmutable('2026-08-31');
        $org = $this->createOrganization();
        $project = $this->createEmptyProject();
        $project->setName('Chantier A');
        $this->addProjectToOrganization($org, $project);

        $alerts = $this->calculator->getAlerts($org, $now);

        $this->assertArrayHasKey('link', $alerts[0]);
    }

    // ── Helper methods ─────────────────────────────────────────

    /**
     * Creates a project with baseline score of 0.
     * Every "passing" component is negated by default so tests start from zero.
     */
    private function createEmptyProject(): Project
    {
        $org = $this->createOrganization();
        $project = new Project($org, '123 rue Test', 'Client Test');

        // Negate "all subs verified" with one unverified sub
        $this->addUnverifiedSubcontractor($project);

        // Negate "no overdue invoices" with one overdue invoice
        $this->addInvoice($project, InvoiceStatus::OVERDUE);

        return $project;
    }

    private function createProject(
        bool $ccqCurrentMonth = false,
        bool $ccqLastMonth = false,
        bool $safetyLogToday = false,
        bool $safetyLogsAllWeek = false,
        bool $allSubsVerified = false,
        bool $noOverdueInvoices = false,
        \DateTimeImmutable $now = new \DateTimeImmutable('2026-08-15'),
    ): Project {
        $org = $this->createOrganization();
        $project = new Project($org, '123 rue Test', 'Client Test');

        // Default negatives for a true-zero baseline
        $this->addUnverifiedSubcontractor($project);
        $this->addInvoice($project, InvoiceStatus::OVERDUE);

        if ($ccqCurrentMonth) {
            $this->addCcqDeclaration($project, (int) $now->format('n'), (int) $now->format('Y'));
        }

        if ($ccqLastMonth) {
            $lastMonth = $now->modify('first day of last month');
            $this->addCcqDeclaration($project, (int) $lastMonth->format('n'), (int) $lastMonth->format('Y'));
        }

        if ($safetyLogToday) {
            $this->addSafetyLog($project, $now);
        }

        if ($safetyLogsAllWeek) {
            $monday = $now->modify('monday this week');
            foreach (range(0, 4) as $offset) {
                $this->addSafetyLog($project, $monday->modify("+{$offset} days"));
            }
        }

        if ($allSubsVerified) {
            $project->getSubcontractors()->clear();
            $this->addVerifiedSubcontractor($project);
        }

        if ($noOverdueInvoices) {
            $project->getInvoices()->clear();
            $this->addInvoice($project, InvoiceStatus::PAID);
        }

        return $project;
    }

    private function addCcqDeclaration(Project $project, int $month, int $year): void
    {
        $declaration = new CCQDeclaration;
        $declaration->setPeriodMonth($month);
        $declaration->setPeriodYear($year);
        $declaration->setProject($project);
        $project->getCcqDeclarations()->add($declaration);
    }

    private function addSafetyLog(Project $project, \DateTimeInterface $date): void
    {
        $log = new SafetyLog;
        $log->setDate($date);
        $log->setProject($project);
        $project->getSafetyLogs()->add($log);
    }

    private function addVerifiedSubcontractor(Project $project): void
    {
        $sub = new Subcontractor;
        $sub->setName('Sous-traitant Vérifié');
        $sub->setVerified(true);
        $project->addSubcontractor($sub);
    }

    private function addUnverifiedSubcontractor(Project $project): void
    {
        $sub = new Subcontractor;
        $sub->setName('Sous-traitant Non Vérifié');
        $sub->setVerified(false);
        $project->addSubcontractor($sub);
    }

    private function addInvoice(Project $project, InvoiceStatus $status): void
    {
        $invoice = new Invoice;
        $invoice->setStatus($status);
        $invoice->setProject($project);
        $project->getInvoices()->add($invoice);
    }

    private function createOrganization(): Organization
    {
        $org = new Organization;
        $org->setName('Org Test');

        return $org;
    }

    private function addProjectToOrganization(Organization $org, Project $project): void
    {
        $org->addProject($project);
    }
}
