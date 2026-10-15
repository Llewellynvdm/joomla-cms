<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  TinyMCE
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Plugin\Editors\TinyMCE\PluginTraits;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Input\Input;
use Joomla\Plugin\Editors\TinyMCE\PluginTraits\XTDButtons;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Exercises editor rendering with the core button and no compatibility plugin.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @since  __DEPLOY_VERSION__
 */
class XTDButtonsTest extends UnitTestCase
{
    /**
     * @testdox  TinyMCE renders matching legacy modal IDs and confirmation options without magic access
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function testLegacyModalWithoutCompatibility(): void
    {
        $button = new Button('registry-name', [
            'name'   => 'LegacyModal',
            'text'   => 'Insert legacy',
            'editor' => 'jform_articletext',
            'modal'  => true,
        ], [
            'height'          => '550px',
            'width'           => '900px',
            'confirmText'     => 'Insert selection',
            'confirmCallback' => 'insertSelection()',
        ]);

        $this->assertFalse(method_exists($button, '__get'));
        $this->assertFalse(method_exists($button, '__set'));

        $this->prepareApplication();

        $editor = new class (Factory::$application, [$button]) {
            use XTDButtons {
                tinyButtons as public renderButtons;
            }

            public function __construct(private $application, private array $buttons)
            {
            }

            private function getButtons($buttons, array $options): array
            {
                return $this->buttons;
            }
        };

        $deprecations = [];
        $this->captureButtonDeprecations($deprecations);
        ob_start();

        try {
            $result = $editor->renderButtons(true, ['editorId' => 'jform_articletext']);
            $html   = ob_get_contents();
        } finally {
            ob_end_clean();
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertTrue($result['names'][0]['bsModal']);
        $this->assertSame('jform_articletext_LegacyModal', $result['names'][0]['id']);
        $this->assertSame($result['names'][0]['id'] . '_modal', $button->get('id'));
        $this->assertStringContainsString('id="' . $button->get('id') . '"', $html);
        $this->assertSame($button->getOptions(), $result['names'][0]['options']);
        $this->assertStringContainsString('onclick="insertSelection()"', $html);
        $this->assertStringContainsString('Insert selection', $html);
    }

    /**
     * @testdox  Shared editor layouts render button options and modal confirmations without the deprecated options bridge
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    public function testSharedButtonLayoutsWithoutCompatibility(): void
    {
        $this->prepareApplication();

        $modern = new Button('insert', ['name' => 'insert', 'text' => 'Insert', 'action' => 'insert'], ['content' => '<p>Example</p>']);
        $legacy = new Button('legacy', [
            'name'   => 'Legacy',
            'text'   => 'Legacy',
            'editor' => 'editor',
            'modal'  => true,
        ], ['confirmText' => 'Confirm', 'confirmCallback' => 'confirmSelection()']);

        $deprecations = [];
        $this->captureButtonDeprecations($deprecations);

        try {
            $html = LayoutHelper::render('joomla.editors.buttons', [$modern, $legacy]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertStringContainsString('data-joomla-editor-button-action="insert"', $html);
        $this->assertStringContainsString('{"content":"<p>Example</p>"}', html_entity_decode($html, ENT_QUOTES));
        $this->assertStringContainsString('data-bs-target="#editor_legacy_modal"', $html);
        $this->assertStringContainsString('id="editor_legacy_modal"', $html);
        $this->assertStringContainsString('onclick="confirmSelection()"', $html);
    }

    /**
     * Supply document assets and replace only the Bootstrap widget renderer.
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    private function prepareApplication(): void
    {
        $assets = new WebAssetManager(new WebAssetRegistry());
        $assets->registerScript('core', 'core.js');
        $document = new Document(['webAssetManager' => $assets]);
        $app      = $this->createStub(CMSWebApplicationInterface::class);
        $app->method('getDocument')->willReturn($document);
        $app->method('getInput')->willReturn(new Input());
        $app->method('isClient')->willReturn(false);
        Factory::$application = $app;

        HTMLHelper::getServiceRegistry()->register('bootstrap', new class () {
            public function renderModal($id, array $options): string
            {
                return '<div id="' . $id . '">' . $options['footer'] . '</div>';
            }
        }, true);
    }

    /**
     * Capture suppressed button deprecations as well as unsuppressed ones.
     *
     * @param   array  $deprecations  The collected button deprecations.
     *
     * @return  void
     * @since   __DEPLOY_VERSION__
     */
    private function captureButtonDeprecations(array &$deprecations): void
    {
        set_error_handler(static function ($severity, $message) use (&$deprecations) {
            if (str_contains($message, 'Button')) {
                $deprecations[] = $message;
            }

            return false;
        }, E_USER_DEPRECATED);
    }
}
