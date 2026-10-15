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
use Joomla\CMS\Document\FactoryInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\WebAsset\WebAssetManager;
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
        \define('COMPAT_JOOMLA_7', true);

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
