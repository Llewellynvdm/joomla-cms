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
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Tests the compatibility boundary for legacy script translations in head data.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlDocumentScriptTextTest extends UnitTestCase
{
    /**
     * @dataProvider compatibilityModes
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testScriptTextIsOnlyExportedInCompatibilityMode(bool $compatibility): void
    {
        if ($compatibility) {
            \define('COMPAT_JOOMLA_7', true);
        }

        $document = $this->createDocument();
        $document->getWebAssetManager()->registerScript('core', 'core.js');

        $application = $this->createMock(CMSApplication::class);
        $application->method('getDocument')->willReturn($document);
        Factory::$application = $application;
        Factory::$language    = $this->createMock(Language::class);
        Factory::$language->method('_')->with('TEST_TRANSLATION', false, true)->willReturn('Translated message');

        Text::script('TEST_TRANSLATION');

        // Document options may contain strings that are not in Text's legacy static store.
        $document->addScriptOptions('joomla.jtext', ['DOCUMENT_ONLY' => 'Document message']);
        $head = $document->getHeadData();

        $this->assertSame(
            ['TEST_TRANSLATION' => 'Translated message', 'DOCUMENT_ONLY' => 'Document message'],
            $head['scriptOptions']['joomla.jtext']
        );

        if ($compatibility) {
            $this->assertSame(['TEST_TRANSLATION' => 'Translated message'], $head['scriptText']);
        } else {
            $this->assertArrayNotHasKey('scriptText', $head);
        }
    }

    /**
     * @dataProvider headRestorationModes
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testScriptOptionsSurviveHeadDataRestoration(bool $compatibility, string $method): void
    {
        if ($compatibility) {
            \define('COMPAT_JOOMLA_7', true);
        }

        $document = $this->createDocument();
        $document->addScriptOptions('joomla.jtext', ['CACHED_TRANSLATION' => 'Cached message']);

        // Head data is serialized by the cache and restored through either of these methods.
        $head     = unserialize(serialize($document->getHeadData()));
        $restored = $this->createDocument();
        $restored->addScriptOptions('joomla.jtext', ['EXISTING_TRANSLATION' => 'Existing message']);
        $restored->{$method}($head);

        $expected = ['CACHED_TRANSLATION' => 'Cached message'];

        if ($method === 'mergeHeadData') {
            $expected = ['EXISTING_TRANSLATION' => 'Existing message'] + $expected;
        }

        $this->assertSame($expected, $restored->getScriptOptions('joomla.jtext'));
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public static function compatibilityModes(): array
    {
        return [
            'compatibility disabled' => [false],
            'compatibility enabled'  => [true],
        ];
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public static function headRestorationModes(): array
    {
        return [
            'set without compatibility'   => [false, 'setHeadData'],
            'merge without compatibility' => [false, 'mergeHeadData'],
            'set with compatibility'      => [true, 'setHeadData'],
            'merge with compatibility'    => [true, 'mergeHeadData'],
        ];
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    private function createDocument(): HtmlDocument
    {
        return new HtmlDocument(['webAssetManager' => new WebAssetManager(new WebAssetRegistry())]);
    }
}
