<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Section;
use App\Repository\SectionRepository;
use App\Service\NewsletterBroadcastMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/{_locale}/admin/newsletters', name: 'admin_newsletters_', requirements: ['_locale' => 'fr|en'])]
final class NewsletterController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(SectionRepository $sectionRepository): Response
    {
        return $this->render('admin/newsletter/index.html.twig', [
            'sections' => $sectionRepository->findNewsletterCampaignSections(),
        ]);
    }

    #[Route('/send/{section}/{test}', name: 'send', methods: ['GET', 'POST'], requirements: ['section' => '\d+', 'test' => 'test'], defaults: ['test' => null])]
    public function send(
        Request $request,
        Section $section,
        NewsletterBroadcastMailer $mailer,
        TranslatorInterface $translator,
        ?string $test,
    ): Response {
        $result = $mailer->send($section, $test === 'test');

        $params = [];
        if (isset($result['count'])) {
            $params['%count%'] = (string) $result['count'];
        }
        if (isset($result['errors'])) {
            $params['%errors%'] = (string) $result['errors'];
        }

        $this->addFlash(
            $result['type'],
            $translator->trans($result['message'], $params, 'messages'),
        );

        $referer = $request->headers->get('referer');

        return $this->redirect($referer !== null && $referer !== ''
            ? $referer
            : $this->generateUrl('admin_newsletters_index', ['_locale' => $request->getLocale()]));
    }
}
