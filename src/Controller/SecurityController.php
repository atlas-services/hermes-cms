<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/{_locale}', defaults: ['_locale' => 'fr'], requirements: ['_locale' => 'fr|en'])]
class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by firewall logout.');
    }

    #[Route(path: '/admin/change_locale/{locale}', name: 'change_locale')]
    public function changeLocale(
        string $locale,
        Request $request,
        RouterInterface $router
    ): Response {
        $session = $request->getSession();

        $session->set('locale', $locale);
        $session->set('_locale', $locale);

        $referer = $request->headers->get('referer');

        if (!$referer) {
            return $this->redirectToRoute('app_login', ['_locale' => $locale]);
        }

        $refererPathInfo = Request::create($referer)->getPathInfo();
        $refererPathInfo = str_replace($request->getScriptName(), '', $refererPathInfo);

        $routeInfos = $router->match($refererPathInfo);

        return $this->redirectToRoute(
            $routeInfos['_route'],
            ['_locale' => $locale]
        );
    }
}
