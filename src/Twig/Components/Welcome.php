<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component rendered on the home page. The increment button demonstrates
 * reactive server-side state without a full page reload (Turbo + Live Component).
 */
#[AsLiveComponent(template: 'components/welcome.html.twig')]
final class Welcome
{
    use DefaultActionTrait;

    #[LiveProp]
    public int $visits = 0;

    #[LiveAction]
    public function increment(): void
    {
        ++$this->visits;
    }
}
