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
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Document\Renderer\Html\MetasRenderer;
use Joomla\CMS\Document\Renderer\Html\ScriptsRenderer;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Event\Dispatcher;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Exercises translation rendering through the real module cache lifecycle.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlDocumentTranslationCacheTest extends UnitTestCase
{
    /**
     * @dataProvider compatibilityModes
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testTranslationsSurviveModuleCacheMissAndHit(bool $compatibility): void
    {
        if ($compatibility) {
            \define('COMPAT_JOOMLA_7', true);
        }

        $document = $this->createDocument();
        $language = $this->createMock(Language::class);
        $language->method('_')->willReturnMap([
            ['MODULE_TRANSLATION', false, true, 'Module message'],
            ['FORMATTED_TRANSLATION', false, true, 'Message from %s'],
        ]);

        $session = $this->createMock(Session::class);
        $session->method('get')->with('user')->willReturn($this->createMock(User::class));
        $session->method('getToken')->willReturn('test-session-token');

        $application = $this->createMock(CMSApplication::class);
        $application->method('getDocument')->willReturnCallback(static function () use (&$document) {
            return $document;
        });
        $application->method('getLanguage')->willReturn($language);
        $application->method('getSession')->willReturn($session);
        $application->method('getDispatcher')->willReturn(new Dispatcher());
        $application->method('get')->willReturnCallback(static function ($key, $default = null) {
            return $default;
        });

        Factory::$application = $application;
        Factory::$language    = $language;
        Factory::$document    = $document;

        // Replace storage only; execute the controller's real reset, capture and restoration logic.
        $cached  = null;
        $storage = $this->createMock(Cache::class);
        $storage->expects($this->exactly(2))->method('get')->with('translation-module')->willReturnCallback(static function () use (&$cached) {
            return $cached ?? false;
        });
        $storage->expects($this->once())->method('lock')->willReturn((object) ['locked' => true, 'locklooped' => false]);
        $storage->expects($this->once())->method('unlock')->willReturn(true);
        $storage->expects($this->once())->method('store')->willReturnCallback(static function ($data) use (&$cached) {
            $cached = $data;

            return true;
        });

        $controller        = new CallbackController(['caching' => true, 'storage' => 'file']);
        $controller->cache = $storage;
        $options           = ['nopathway' => 1, 'nohead' => 0, 'nomodules' => 1, 'modulemode' => 1, 'mergehead' => 1];
        $calls             = 0;
        $callback          = static function () use (&$calls) {
            $calls++;
            Text::script('MODULE_TRANSLATION');
            Text::sprintf('FORMATTED_TRANSLATION', 'module', ['script' => true]);
            echo '<div>Module output</div>';

            return 'module-result';
        };

        $expected = [
            'PAGE_TRANSLATION'      => 'Page message',
            'MODULE_TRANSLATION'    => 'Module message',
            'FORMATTED_TRANSLATION' => 'Message from module',
        ];

        foreach (['miss', 'hit'] as $request) {
            // A fresh document on the hit prevents assets or translations leaking from the miss.
            if ($request === 'hit') {
                $document          = $this->createDocument();
                Factory::$document = $document;
            }

            $document->addScriptOptions('joomla.jtext', ['PAGE_TRANSLATION' => 'Page message']);
            $this->assertFalse($document->getWebAssetManager()->isAssetActive('script', 'core'));

            ob_start();

            try {
                $result = $controller->get($callback, [], 'translation-module', true, $options);
                $output = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            $this->assertSame('module-result', $result);
            $this->assertSame('<div>Module output</div>', $output);
            $this->assertSame($expected, $document->getScriptOptions('joomla.jtext'));
            $this->assertTrue($document->getWebAssetManager()->isAssetActive('script', 'core'));

            $this->assertRenderedTranslations($document, $expected);
        }

        $this->assertSame(1, $calls, 'A cache hit must restore translations without executing the module again.');

        $payload = unserialize($cached);
        $this->assertSame(
            array_diff_key($expected, ['PAGE_TRANSLATION' => true]),
            $payload['output']['head']['scriptOptions']['joomla.jtext'],
            'The module cache must not capture translations belonging to the surrounding page.'
        );
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
    private function createDocument(): HtmlDocument
    {
        $document = new HtmlDocument(['webAssetManager' => new WebAssetManager(new WebAssetRegistry())]);
        $document->getWebAssetManager()->registerScript('core', 'https://example.test/core.js');

        return $document;
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    private function assertRenderedTranslations(HtmlDocument $document, array $expected): void
    {
        // Avoid template favicon discovery, which is unrelated to translation rendering.
        $document->addFavicon('https://example.test/favicon.ico');
        (new MetasRenderer($document))->render('');
        $html = (new ScriptsRenderer($document))->render('');

        $this->assertSame(1, preg_match('/<script\b[^>]*class="joomla-script-options new"[^>]*>(.*?)<\/script>/s', $html, $matches));
        $this->assertSame($expected, json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['joomla.jtext']);
        $this->assertStringContainsString('src="https://example.test/core.js"', $html);
        $this->assertLessThan(strpos($html, 'src="https://example.test/core.js"'), strpos($html, 'joomla-script-options new'));
    }
}
