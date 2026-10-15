<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  Document
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Libraries\Cms\Document;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Document\FactoryInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\User\User;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\DI\Container;
use Joomla\Input\Input;
use Joomla\Module\Menu\Site\Dispatcher\Dispatcher;
use Joomla\Plugin\Behaviour\Compat7\Extension\Compat7;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Tests the compatibility boundary for counting children of the active menu item.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlDocumentMenuChildrenCompatibilityTest extends UnitTestCase
{
    /**
     * @since  __DEPLOY_VERSION__
     */
    public function testCountingMenuChildrenRequiresCompatibility(): void
    {
        Factory::$application = null;

        $document = $this->createDocument();

        $this->assertFalse(\defined('COMPAT_JOOMLA_7'));
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('compatibility');

        $document->countMenuChildren();
    }

    /**
     * @dataProvider menuChildren
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testCompatibilityPreservesMenuChildrenCount(?int $children, int $expected): void
    {
        new Compat7(['params' => ['classes_aliases' => 0, 'legacy_classes' => 0]]);

        $this->assertTrue(\defined('COMPAT_JOOMLA_7'));

        $active = null;

        if ($children !== null) {
            $active = new MenuItem();

            for ($i = 0; $i < $children; $i++) {
                $active->addChild(new MenuItem());
            }
        }

        $menu = $this->createMock(AbstractMenu::class);
        $menu->expects($this->once())->method('getActive')->willReturn($active);

        $application = $this->createMock(CMSApplication::class);
        $application->expects($this->once())->method('getMenu')->willReturn($menu);

        Factory::$application = $application;
        $document             = $this->createDocument();
        $deprecations         = [];

        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->assertSame($expected, $document->countMenuChildren());
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('countMenuChildren()', $deprecations[0]);
        $this->assertStringContainsString('deprecated', $deprecations[0]);
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public static function menuChildren(): array
    {
        return [
            'no active menu item' => [null, 0],
            'no children'         => [0, 0],
            'multiple children'   => [3, 3],
        ];
    }

    /**
     * Exercises the stock dispatcher, helper and layout without the compatibility plugin.
     *
     * @dataProvider activeMenuItems
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testMenuModuleRendersWithoutCompatibility(bool $hasActiveItem): void
    {
        $this->assertFalse(\defined('COMPAT_JOOMLA_7'));

        $parent = new MenuItem([
            'id'        => 101,
            'parent_id' => 1,
            'menutype'  => 'mainmenu',
            'title'     => 'Parent menu',
            'type'      => 'url',
            'link'      => 'https://example.org/parent',
            'level'     => 1,
            'tree'      => [101],
        ]);
        $child = new MenuItem([
            'id'        => 102,
            'parent_id' => 101,
            'menutype'  => 'mainmenu',
            'title'     => 'Child & detail',
            'type'      => 'url',
            'link'      => 'https://example.org/child',
            'level'     => 2,
            'tree'      => [101, 102],
        ]);
        $parent->addChild($child);

        // Only the persistence boundary is replaced; active/default selection and filtering are real.
        $menu = $this->getMockBuilder(AbstractMenu::class)
            ->onlyMethods(['getMenu'])
            ->getMockForAbstractClass();
        $menu->method('getMenu')->willReturn([101 => $parent, 102 => $child]);
        $menu->setDefault(101);

        if ($hasActiveItem) {
            $menu->setActive(101);
        }

        $this->assertSame($hasActiveItem ? $parent : null, $menu->getActive());

        $cache        = $this->createMock(OutputController::class);
        $cache->cache = $this->createMock(Cache::class);
        $cache->cache->method('contains')->willReturn(false);
        $cache->method('store')->willReturn(true);

        $cacheFactory = $this->createMock(CacheControllerFactoryInterface::class);
        $cacheFactory->method('createCacheController')->with('output', ['defaultgroup' => 'mod_menu'])->willReturn($cache);

        Factory::$container = new Container();
        Factory::$container->set(CacheControllerFactoryInterface::class, $cacheFactory);

        // Read the shipped asset definitions so this also works before the frontend build is installed.
        $registry = new WebAssetRegistry();
        $registry->addRegistryFile('media_source/mod_menu/joomla.asset.json');
        $document = new HtmlDocument(['webAssetManager' => new WebAssetManager($registry)]);
        $input    = new Input();
        $identity = $this->createMock(User::class);
        $identity->method('getAuthorisedViewLevels')->willReturn([1]);

        $application = $this->getMockBuilder(CMSApplication::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['doExecute', 'get', 'getMenu', 'getDocument', 'getInput', 'getIdentity', 'getLanguage', 'getName', 'isClient', 'getTemplate'])
            ->addMethods(['getLanguageFilter'])
            ->getMock();
        $application->method('getMenu')->willReturn($menu);
        $application->method('getDocument')->willReturn($document);
        $application->method('getInput')->willReturn($input);
        $application->method('getIdentity')->willReturn($identity);
        $application->method('getLanguage')->willReturn(new Language('en-GB'));
        $application->method('getName')->willReturn('site');
        $application->method('isClient')->with('site')->willReturn(true);
        $application->method('getLanguageFilter')->willReturn(false);
        $application->method('getTemplate')->willReturnCallback(static function (bool $params = false) {
            return $params ? (object) ['template' => 'cassiopeia', 'parent' => ''] : 'cassiopeia';
        });

        Factory::$application = $application;

        $module = (object) [
            'id'     => 10,
            'module' => 'mod_menu',
            'params' => '{"menutype":"mainmenu","startLevel":1,"showAllChildren":1}',
        ];
        $dispatcher = new Dispatcher($module, $application, $input);
        $dispatcher->setHelperFactory(new HelperFactory('Joomla\\Module\\Menu\\Site\\Helper'));

        ob_start();

        try {
            $dispatcher->dispatch();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(1, $xpath->query('//ul[@id="mod-menu10"]/li[contains(@class, "item-101") and contains(@class, "current") and contains(@class, "parent")]')->length);
        $this->assertSame(1, $xpath->query('//li[contains(@class, "item-101")]/ul/li[contains(@class, "item-102")]/a[@href="https://example.org/child"]')->length);
        $this->assertStringContainsString('Child & detail', $dom->textContent);
        $this->assertFalse(\defined('COMPAT_JOOMLA_7'));
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public static function activeMenuItems(): array
    {
        return [
            'active parent with children' => [true],
            'no active item uses default' => [false],
        ];
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    private function createDocument(): HtmlDocument
    {
        return new HtmlDocument([
            'factory'         => $this->createMock(FactoryInterface::class),
            'webAssetManager' => $this->createMock(WebAssetManager::class),
        ]);
    }
}
