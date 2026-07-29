<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Living styleguide — renders the authenticated app shell, design tokens,
 * and the loading/empty/error state components. Used as the test target for the
 * layout/state card and as visual documentation.
 *
 * Gated to non-prod environments so it never ships publicly.
 */
final class StyleguideController extends AbstractController
{
    /**
     * @param KernelInterface $kernel
     * @return Response
     */
    #[Route('/_styleguide', name: 'app_styleguide')]
    public function index(KernelInterface $kernel): Response
    {
        if ('prod' === $kernel->getEnvironment()) {
            return $this->redirect('/');
        }

        return $this->render('styleguide/index.html.twig');
    }
}
