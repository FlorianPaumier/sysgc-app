<?php

declare(strict_types=1);

namespace App\Compliance\Application;

use App\Project\Domain\Model\Project;
use App\Workforce\Domain\Model\HoursEntry;

interface HoursEntryReader
{
    /** @return iterable<HoursEntry> */
    public function findForProjectAndMonth(Project $project, int $month, int $year): iterable;
}
