<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
new Symfony\Component\Dotenv\Dotenv()->bootEnv(dirname(__DIR__, 2).'/.env');
if (($_SERVER['APP_ENV'] ?? '') !== 'dev') {
    throw new LogicException('Audit browser fixtures are only available in dev.');
}
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$rawToken = bin2hex(random_bytes(32));
$org = new App\Identity\Domain\Model\Organization()->setName('Audit Browser '.bin2hex(random_bytes(4)))->setRbqLicense('RBQ-1234-5678-90')->setAddress(new App\Identity\Domain\Model\Address());
$user = new App\Identity\Domain\Model\User()->setEmail('audit-'.bin2hex(random_bytes(8)).'@example.test')->setOrganization($org)->setRoles(['ROLE_OWNER'])->setIsVerified(true);
$project = new App\Project\Domain\Model\Project($org, 'Audit Site', 'Audit Client');
$project->setStartDate(new DateTime('today'));
$org->addProject($project);
$worker = new App\Workforce\Domain\Model\Worker()->setName('Audit Worker')->setOrganization($org)->setCcqTrade(App\Workforce\Domain\Enum\CcqTrade::Electrician)->setCcqRegion(App\Workforce\Domain\Enum\CcqRegion::Montreal);
$now = new DateTimeImmutable('today');
$monday = $now->modify('first day of this month')->modify('next monday');
$entry = new App\Workforce\Domain\Model\HoursEntry()->setWorker($worker)->setProject($project)->setWeekStart(DateTime::createFromImmutable($monday))->setHours(['monday' => 8, 'tuesday' => 8, 'wednesday' => 8, 'thursday' => 8, 'friday' => 8]);
$link = new App\Identity\Domain\Model\MagicLink()->setOwner($user)->setTokenHash(hash('sha256', $rawToken))->setMagicLinkTokenExpiresAt(new DateTimeImmutable('+15 minutes'));
foreach ([$org, $user, $project, $worker, $entry, $link] as $entity) {
    $em->persist($entity);
}
$em->flush();
echo json_encode(['projectId' => (string) $project->getId(), 'email' => $user->getEmail(), 'token' => $rawToken, 'date' => $now->format('Y-m-d'), 'monday' => $monday->format('Y-m-d')], JSON_THROW_ON_ERROR);
