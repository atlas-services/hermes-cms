<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\DataFixtures\MenuFixtures;
use App\Entity\Menu;
use App\Tests\Base\BaseKernelTestCase;
use App\Validator\Constraints\UniqueRootMenuName;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UniqueRootMenuNameValidatorTest extends BaseKernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->initDatabase();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    protected function loadFixtures(): array
    {
        return [
            new MenuFixtures(),
        ];
    }

    public function testDuplicateRootMenuNameIsRejected(): void
    {
        $menu = new Menu();
        $menu->setName('Root 1');
        $menu->setLocale('fr');

        $violations = $this->validator->validate($menu, new UniqueRootMenuName());

        $this->assertCount(1, $violations);
        $this->assertSame('name', $violations->get(0)->getPropertyPath());
        $this->assertSame('menu.name_locale_exists', $violations->get(0)->getMessage());
    }

    public function testDuplicateRootMenuNameIsCaseInsensitive(): void
    {
        $menu = new Menu();
        $menu->setName('root 1');
        $menu->setLocale('fr');

        $violations = $this->validator->validate($menu, new UniqueRootMenuName());

        $this->assertCount(1, $violations);
    }

    public function testSubMenuWithExistingRootNameIsAllowed(): void
    {
        $parent = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Root 1']);
        $this->assertInstanceOf(Menu::class, $parent);

        $menu = new Menu();
        $menu->setName('Root 1');
        $menu->setLocale('fr');
        $menu->setParent($parent);

        $violations = $this->validator->validate($menu, new UniqueRootMenuName());

        $this->assertCount(0, $violations);
    }

    public function testEditingSameRootMenuKeepsItsName(): void
    {
        $menu = $this->em->getRepository(Menu::class)->findOneBy(['name' => 'Root 1']);
        $this->assertInstanceOf(Menu::class, $menu);

        $violations = $this->validator->validate($menu, new UniqueRootMenuName());

        $this->assertCount(0, $violations);
    }
}
