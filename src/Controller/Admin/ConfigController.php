<?php

namespace App\Controller\Admin;

use App\Entity\Config;
use App\Form\ConfigType;
use App\Repository\ConfigRepository;
use App\Service\ConfigGlobalsProvider;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}/admin/config')]
class ConfigController extends AbstractController
{
    public function __construct(
        private readonly ConfigGlobalsProvider $configGlobalsProvider,
    ) {
    }

    #[Route(path: '/navbar-type/{type}', name: 'config_navbar_type', methods: ['GET'])]
    public function switchType(
        Request $request,
        ManagerRegistry $doctrine,
        ConfigRepository $configRepository,
        string $type
    ): Response {
        $navBar = $configRepository->findOneBy(['code' => 'nav_bar']);

        if (!$navBar instanceof Config) {
            throw $this->createNotFoundException('Config not found');
        }

        $navBar->setValue($type);

        $entityManager = $doctrine->getManager();
        $entityManager->flush();

        return $this->redirect($request->headers->get('referer') ?? '/');
    }

    #[Route(path: '/{id}/toggle-active', name: 'config_toggle_active', methods: ['POST'])]
    public function toggleActive(
        Request $request,
        ManagerRegistry $doctrine,
        Config $config
    ): Response {
        if (!$this->isCsrfTokenValid('toggle-active' . $config->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 400);
        }

        $config->setActive(!$config->isActive());
        if (in_array($config->getCode(), ['topbar_dismiss_once', 'nav_left_open_on_load'], true)) {
            $config->setValue($config->isActive() ? '1' : '0');
        }

        $doctrine->getManager()->flush();

        return $this->json([
            'success' => true,
            'active' => $config->isActive(),
            'statusText' => $config->isActive() ? 'global.active' : 'global.inactive'
        ]);
    }

    #[Route(path: '/{type}', name: 'config_index', methods: ['GET'])]
    public function index(
        ManagerRegistry $doctrine,
        ?string $type
    ): Response {
        $type = $type === 'undefined' ? null : $type;

        /** @var ConfigRepository $configRepository */
        $configRepository = $doctrine->getRepository(Config::class);

        $configs = $configRepository->getConfigByTypeOrderByPosition($type);

        $array = [
            'configs' => $configs,
        ];

        $array = $this->mergeActiveConfig($doctrine, $array);

        return $this->render('admin/config/index.html.twig', $array);
    }

    #[Route(path: '/{id}', name: 'config_show', methods: ['GET'])]
    public function show(ManagerRegistry $doctrine, Config $config): Response
    {
        $array = [
            'config' => $config,
        ];

        $array = $this->mergeActiveConfig($doctrine, $array);

        return $this->render('admin/config/show.html.twig', $array);
    }

    #[Route(path: '/{id}/edit', name: 'config_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ManagerRegistry $doctrine,
        Config $config,
        Filesystem $filesystem
    ): Response {
        $configInit = clone $config;

        $typeImage = explode(',', (string) $this->getParameter('hermes_list_type_image'));

        $options = [
            'code_disabled' => !$this->isGranted('ROLE_SUPER_ADMIN'),
            'show_active' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'disable_type' => true,
        ];

        if (in_array($config->getCode(), $typeImage, true)) {
            $options['type_image'] = true;
        }

        $form = $this->createForm(ConfigType::class, $config, $options);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $doctrine->getManager()->flush();

            $type = $config->getType();

            return $this->redirectToRoute('config_index', [
                'type' => $type,
            ]);
        }

        $array = [
            'config' => $config,
            'form' => $form->createView(),
        ];

        $array = $this->mergeActiveConfig($doctrine, $array);

        return $this->render('admin/config/edit.html.twig', $array);
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return array<string, mixed>
     */
    protected function mergeActiveConfig(ManagerRegistry $doctrine, array $array): array
    {
        unset($doctrine);

        return array_merge($array, $this->configGlobalsProvider->getConfigs());
    }
}
