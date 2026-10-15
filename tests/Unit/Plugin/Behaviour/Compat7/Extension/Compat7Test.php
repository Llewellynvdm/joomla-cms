<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  Compat7
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Plugin\Behaviour\Compat7\Extension;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Editor\Button\ButtonInterface;
use Joomla\Plugin\Behaviour\Compat7\Extension\Compat7;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Tests class loading and editor button compatibility in fresh PHP processes.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @since  __DEPLOY_VERSION__
 */
class Compat7Test extends UnitTestCase
{
    /**
     * @testdox  Legacy property access delegates to the same storage as modern accessors
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function testLegacyButtonPropertyAccess(): void
    {
        $this->assertFalse(class_exists(Button::class, false));

        new Compat7(['params' => ['legacy_classes' => 1]]);

        $button       = new Button('registry-name', ['name' => 'modal-name']);
        $deprecations = [];

        $this->assertInstanceOf(ButtonInterface::class, $button);
        $this->assertTrue((new \ReflectionClass($button))->isFinal());
        $this->assertSame('registry-name', $button->getButtonName());

        set_error_handler(static function ($severity, $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $this->assertSame('modal-name', $button->name);
            $this->assertNull($button->missing);
            $button->id = 'editor_modal-name_modal';
            $this->assertSame('editor_modal-name_modal', $button->get('id'));

            $button->set('text', 'Insert');
            $this->assertSame('Insert', $button->text);
        } finally {
            restore_error_handler();
        }

        $this->assertCount(4, $deprecations);

        foreach ($deprecations as $message) {
            $this->assertStringContainsString('Property access is deprecated', $message);
            $this->assertStringContainsString('use get/set methods', $message);
        }
    }

    /**
     * @testdox  Disabling legacy classes leaves the core button implementation active
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function testLegacyClassesDisabled(): void
    {
        new Compat7(['params' => ['legacy_classes' => 0]]);

        $button = new Button('modern');

        $this->assertFalse(method_exists($button, '__get'));
        $this->assertFalse(method_exists($button, '__set'));
        $this->assertSame($button, $button->set('text', 'Insert'));
        $this->assertSame('Insert', $button->get('text'));
    }

    /**
     * @testdox  A button loaded before the plugin is retained without a class redeclaration
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function testCoreButtonAlreadyLoaded(): void
    {
        $button = new Button('already-loaded', ['text' => 'Insert']);

        new Compat7(['params' => ['legacy_classes' => 1]]);

        $this->assertFalse(method_exists($button, '__get'));
        $this->assertFalse(method_exists($button, '__set'));
        $this->assertSame('Insert', $button->get('text'));
        $this->assertSame('next', (new Button('next'))->getButtonName());
    }
}
