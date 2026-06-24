<?php

namespace App\Command;

use App\Entity\Config;
use App\Entity\Template;
use App\Service\WelcomeSiteInitializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'app:init-hermes')]
class InitHermesTemplatesAndConfigCommand extends Command
{
    private const OBSOLETE_CONFIGS = [
        'site' => [
            'accueil',
            'affiche_img_hermes',
            'bg_image',
            'chevron_accueil_bgcolor',
            'chevron_accueil_color',
            'chevron_accueil_opacity',
            'template',
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private WelcomeSiteInitializer $welcomeSiteInitializer,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Initiate Hermes Templates and Configs.');
      }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $nb = $this->initTemplates();
        $output->writeln(sprintf(" %s Templates created successfully ", $nb));

        $removed = $this->removeObsoleteConfigs();
        $output->writeln(sprintf(" %s obsolete Configs removed ", $removed));

        $nb = $this->initConfig();
        $output->writeln(sprintf(" %s Configs created successfully ", $nb));

        $welcome = $this->welcomeSiteInitializer->initializeIfEmpty();
        if ($welcome['created']) {
            $output->writeln(sprintf(' Welcome home ACCUEIL created (menu id %s) ', $welcome['menu_id']));
        } else {
            $output->writeln(' Welcome home skipped (menus already exist) ');
        }

        return Command::SUCCESS;
    }

    public function initTemplates() : int
    {
        $nb = 0;
        $templates = $this->params->get('templates');

        if (!$templates) {
            throw new InvalidArgumentException('No templates configured.');
        }

        foreach ($templates as $template) {
            $db_template = $this->entityManager->getRepository(Template::class)->findOneBy(['code' => $template['code']]);
            $active = (bool) ($template['active'] ?? true);

            if ($db_template === null) {
                $newTemplate = new Template();
                $newTemplate->setType($template['type']);
                $newTemplate->setCode($template['code']);
                $newTemplate->setName($template['name']);
                $newTemplate->setSummary($template['summary']);
                $newTemplate->setActive($active);

                $this->entityManager->persist($newTemplate);
                $nb++;
                continue;
            }

            $db_template->setActive($active);
            $db_template->setType($template['type']);
            $db_template->setName($template['name']);
            $db_template->setSummary($template['summary']);
        }

        $this->entityManager->flush();
        return $nb;
    }

    public function initConfig() : int
    {
        $nb = 0;
        $configs = $this->params->get('configs');

        // Configs booking : fusionnées au compile si le bundle est installé (voir hermes_booking_configs.yaml).
        if (!isset($configs['booking']) && ($bookingDefaults = \App\Services\Booking\HermesBookingConfigDefaults::load()) !== []) {
            $configs = array_merge($configs, $bookingDefaults);
        }

        if (!$configs) {
            throw new InvalidArgumentException('No config configured.');
        }

        foreach($configs as $type => $config){

            foreach($config as $code => $conf){
                $db_config = $this->entityManager->getRepository(Config::class)->findOneBy(['type' => $type, 'code' => $code]);
                if(is_null($db_config)){
                    // Créer  config
                    $newConfig = new Config();
                    $newConfig->setActive(true);
                    $newConfig->setType($type);
                    $newConfig->setCode($code);
                    $newConfig->setSummary($conf['summary']);
                    $newConfig->setValue($conf['value']);
                    if(!isset($conf['position'])){
                        $conf['position'] = 99;
                    }
                    $newConfig->setPosition($conf['position']);

                    $this->entityManager->persist($newConfig);
                    $nb++;
                    continue;
                }

                $db_config->setSummary($conf['summary']);
                $db_config->setPosition($conf['position'] ?? 99);
            }

        }

        $this->entityManager->flush();
        return $nb;
    }

    public function removeObsoleteConfigs(): int
    {
        $nb = 0;
        $repository = $this->entityManager->getRepository(Config::class);

        foreach (self::OBSOLETE_CONFIGS as $type => $codes) {
            foreach ($codes as $code) {
                $config = $repository->findOneBy(['type' => $type, 'code' => $code]);
                if ($config instanceof Config) {
                    $this->entityManager->remove($config);
                    $nb++;
                }
            }
        }

        $this->entityManager->flush();

        return $nb;
    }

}
