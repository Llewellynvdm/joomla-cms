<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  Editor
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Libraries\Cms\Editor\Button;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Editor\Button\ButtonInterface;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Tests the editor button without the compatibility plugin.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @since  __DEPLOY_VERSION__
 */
class ButtonTest extends UnitTestCase
{
    /**
     * @testdox  Modern accessors preserve property values independently of the registry name
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function testAccessorsWithoutMagicMethods(): void
    {
        $button = new Button('registry-name', ['name' => 'modal-name', 'nullable' => null], ['height' => '550px']);

        $this->assertInstanceOf(ButtonInterface::class, $button);
        $this->assertFalse(method_exists($button, '__get'));
        $this->assertFalse(method_exists($button, '__set'));
        $this->assertSame('registry-name', $button->getButtonName());
        $this->assertSame('modal-name', $button->get('name'));
        $this->assertNull($button->get('nullable', 'fallback'));
        $this->assertSame('fallback', $button->get('missing', 'fallback'));
        $this->assertSame($button, $button->set('id', 'editor_modal-name_modal'));
        $this->assertSame('editor_modal-name_modal', $button->get('id'));
        $this->assertSame(['height' => '550px'], $button->getOptions());
        $this->assertSame($button, $button->setOptions(['height' => '600px']));
        $this->assertSame(['height' => '600px'], $button->getOptions());
    }
}
