<?php

/**
 * @package     Joomla.UnitTest
 * @subpackage  Language
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Tests\Unit\Libraries\Cms\Language;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\WebAsset\WebAssetRegistry;
use Joomla\Tests\Unit\UnitTestCase;

/**
 * Tests JavaScript translation registration through every Text producer.
 *
 * @since  __DEPLOY_VERSION__
 */
class TextScriptTest extends UnitTestCase
{
    private $application;

    private $language;

    private array $strings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = Factory::$application;
        $this->language    = Factory::$language;
        $this->strings     = Text::getScriptStrings();

        (new \ReflectionProperty(Text::class, 'strings'))->setValue(null, ['LEGACY_ONLY' => 'Legacy message']);
        Factory::$language = $this->createMock(Language::class);
    }

    protected function tearDown(): void
    {
        Factory::$application = $this->application;
        Factory::$language    = $this->language;
        (new \ReflectionProperty(Text::class, 'strings'))->setValue(null, $this->strings);

        parent::tearDown();
    }

    /**
     * @dataProvider scriptProducers
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testEveryProducerRegistersItsTranslatedValue(
        string $method,
        array $arguments,
        array $translations,
        array $expectedStrings,
        $expectedReturn,
        bool $jsSafe = false,
        bool $interpretBackSlashes = true
    ): void {
        $document = $this->createDocument();
        $document->addScriptOptions('joomla.jtext', ['DOCUMENT_ONLY' => 'Document message']);
        $document->addScriptOptions('unrelated', ['enabled' => true]);
        $this->setApplicationDocument($document);

        Factory::$language->method('hasKey')->willReturnCallback(static fn ($key) => isset($translations[$key]));
        Factory::$language->method('getPluralSuffixes')->willReturn(['MORE']);
        Factory::$language->expects($this->exactly(\count($translations)))->method('_')
            ->willReturnCallback(function ($key, $safe, $interpret) use ($translations, $jsSafe, $interpretBackSlashes) {
                $this->assertSame($jsSafe, $safe);
                $this->assertSame($interpretBackSlashes, $interpret);
                $this->assertArrayHasKey($key, $translations);

                return $translations[$key];
            });

        $result = Text::{$method}(...$arguments);

        $this->assertSame($expectedReturn, $result);
        $this->assertSame(['LEGACY_ONLY' => 'Legacy message'] + $expectedStrings, Text::getScriptStrings());
        $this->assertSame(['DOCUMENT_ONLY' => 'Document message'] + $expectedStrings, $document->getScriptOptions('joomla.jtext'));
        $this->assertSame(['enabled' => true], $document->getScriptOptions('unrelated'));
        $this->assertTrue($document->getWebAssetManager()->isAssetActive('script', 'core'));
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public static function scriptProducers(): array
    {
        return [
            'underscore options'                   => ['_', ['KEY', ['script' => true]], ['KEY' => 'Translated'], ['KEY' => 'Translated'], 'KEY'],
            'underscore positional flags and case' => [
                '_', ['MixedKey', true, false, true], ['MixedKey' => "Safe \\' value"], ['MixedKey' => "Safe \\' value"], 'MixedKey', true, false,
            ],
            'alternate key'      => ['alt', ['KEY', 'context', ['script' => true]], ['KEY_context' => 'Alternate'], ['KEY_context' => 'Alternate'], 'KEY_context'],
            'alternate fallback' => ['alt', ['KEY', 'missing', false, true, true], ['KEY' => 'Fallback'], ['KEY' => 'Fallback'], 'KEY'],
            'formatted value'    => [
                'sprintf', ['FORMAT', 'Ada', 42, ['script' => true]], ['FORMAT' => 'Hello %s: %d'], ['FORMAT' => 'Hello Ada: 42'], 'FORMAT',
            ],
            'formatted translation flags' => [
                'sprintf', ['FORMAT', 'Ada', ['script' => true, 'jsSafe' => true, 'interpretBackSlashes' => false]],
                ['FORMAT'                              => 'Hello %s'], ['FORMAT' => 'Hello Ada'], 'FORMAT', true, false,
            ],
            'plural count key' => [
                'plural', ['ITEMS', 2, ['script' => true]], ['ITEMS_2' => '%d items'], ['ITEMS_2' => '2 items'], 'ITEMS_2',
            ],
            'plural suffix key' => [
                'plural', ['ITEMS', 3, ['script' => true]], ['ITEMS_MORE' => '%d items'], ['ITEMS_MORE' => '3 items'], 'ITEMS_MORE',
            ],
            'plural fallback and flags' => [
                'plural', ['ITEMS', 3, ['script' => true, 'jsSafe' => true, 'interpretBackSlashes' => false]],
                ['ITEMS'                         => '%d items'], ['ITEMS' => '3 items'], 'ITEMS', true, false,
            ],
            'comma formatted translation' => [
                '_', ['FORMAT,NAME', ['script' => true]], ['FORMAT' => 'Hello %s', 'NAME' => 'Ada'], ['Ada' => 'Ada'], 'Hello Ada',
            ],
            'comma named placeholder and flags' => [
                '_', ['FORMAT,NAME', ['script' => true, 'jsSafe' => true, 'interpretBackSlashes' => false]],
                ['FORMAT'                      => 'Hello [[%1:person]]', 'NAME' => 'Ada'], ['Ada' => 'Ada'], 'Hello Ada', true, false,
            ],
            'script normalizes its key' => [
                'script', ['lower_key'], ['lower_key' => 'Translated'], ['LOWER_KEY' => 'Translated'],
                ['LEGACY_ONLY'                        => 'Legacy message', 'LOWER_KEY' => 'Translated'],
            ],
            'script translation flags' => [
                'script', ['KEY', ['jsSafe' => true, 'interpretBackSlashes' => false]], ['KEY' => 'Translated'], ['KEY' => 'Translated'],
                ['LEGACY_ONLY'              => 'Legacy message', 'KEY' => 'Translated'], true, false,
            ],
        ];
    }

    /**
     * @dataProvider nonWebContexts
     *
     * @since  __DEPLOY_VERSION__
     */
    public function testLegacyProducersRemainUsableWithoutAWebDocument(bool $console): void
    {
        Factory::$application = $console ? $this->createMock(ConsoleApplication::class) : null;
        Factory::$language->method('_')->willReturnMap([
            ['KEY', false, true, 'Translated'],
            ['FORMAT', false, true, 'Hello %s'],
            ['ITEMS', false, true, '%d items'],
        ]);
        Factory::$language->method('getPluralSuffixes')->willReturn([]);

        $this->assertSame('KEY', Text::_('KEY', ['script' => true]));
        $this->assertSame('KEY', Text::alt('KEY', 'missing', ['script' => true]));
        $this->assertSame('FORMAT', Text::sprintf('FORMAT', 'Ada', ['script' => true]));
        $this->assertSame('ITEMS', Text::plural('ITEMS', 2, ['script' => true]));
        $this->assertSame('Hello Translated', Text::_('FORMAT,KEY', ['script' => true]));
        $this->assertSame([
            'LEGACY_ONLY' => 'Legacy message',
            'KEY'         => 'Translated',
            'FORMAT'      => 'Hello Ada',
            'ITEMS'       => '2 items',
            'Translated'  => 'Translated',
        ], Text::getScriptStrings());
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public static function nonWebContexts(): array
    {
        return ['no application' => [false], 'console application' => [true]];
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public function testRegistrationUsesCurrentDocumentStateAfterResetOrReplacement(): void
    {
        $document = $this->createDocument();
        $this->setApplicationDocument($document);
        Factory::$language->method('_')->willReturnOnConsecutiveCalls('First', 'Second', 'Third');

        Text::_('KEY', ['script' => true]);
        $document->resetHeadData();
        $document->getWebAssetManager()->reset();
        $document->addScriptOptions('joomla.jtext', ['RESTORED' => 'Cached message', 'KEY' => 'Cached translation']);
        Text::_('KEY', ['script' => true]);

        $this->assertSame(['RESTORED' => 'Cached message', 'KEY' => 'Second'], $document->getScriptOptions('joomla.jtext'));
        $this->assertTrue($document->getWebAssetManager()->isAssetActive('script', 'core'));

        $replacement = $this->createDocument();
        $this->setApplicationDocument($replacement);
        Text::_('KEY', ['script' => true]);

        $this->assertSame(['KEY' => 'Third'], $replacement->getScriptOptions('joomla.jtext'));
        $this->assertSame(['RESTORED' => 'Cached message', 'KEY' => 'Second'], $document->getScriptOptions('joomla.jtext'));
        $this->assertSame(['LEGACY_ONLY' => 'Legacy message', 'KEY' => 'Third'], Text::getScriptStrings());
    }

    /**
     * @since  __DEPLOY_VERSION__
     */
    public function testScriptStillRequiresAnApplication(): void
    {
        Factory::$application = null;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to start application');

        Text::script('KEY');
    }

    private function createDocument(): HtmlDocument
    {
        $document = new HtmlDocument(['webAssetManager' => new WebAssetManager(new WebAssetRegistry())]);
        $document->getWebAssetManager()->registerScript('core', 'core.js');

        return $document;
    }

    private function setApplicationDocument(HtmlDocument $document): void
    {
        Factory::$application = $this->createMock(CMSApplication::class);
        Factory::$application->method('getDocument')->willReturn($document);
    }
}
