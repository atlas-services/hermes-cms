<?php

namespace App\Controller;

use App\Form\ForgotPasswordFormType;
use App\Form\ResetPasswordFormType;
use App\Service\PasswordResetService;
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

    #[Route('/forgotten_password', name: 'app_forgotten_password')]
    #[Route('/re-init-password', name: 'app_init_password')]
    public function forgottenPassword(Request $request, PasswordResetService $passwordResetService): Response
    {
        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();

            try {
                $passwordResetService->requestPasswordReset($email, $request->getLocale());
            } catch (\Throwable) {
                $this->addFlash('danger', 'security.forgot_password.email_send_failed');

                return $this->redirectToRoute('app_forgotten_password', ['_locale' => $request->getLocale()]);
            }

            $this->addFlash('success', 'security.forgot_password.email_sent');

            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/forgotten_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reset_password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        Request $request,
        PasswordResetService $passwordResetService,
        string $token
    ): Response {
        $user = $passwordResetService->findUserByResetToken($token);

        if (!$user) {
            $this->addFlash('danger', 'security.reset_password.invalid_token');

            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordResetService->resetPassword($user, (string) $form->get('plainPassword')->getData());
            $this->addFlash('success', 'security.reset_password.success');

            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form,
            'token' => $token,
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
