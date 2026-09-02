<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Map Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace UhifadhiLabs\Map\Tests\Integration\Asset;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use UhifadhiLabs\Map\UhifadhiLabsMapBundle;

/**
 * THE ASSET SEAM, end to end in a real kernel.
 *
 * This is the test that would have saved the extraction. Three scripts moved out
 * of the host application into a bundle, and the whole product's maps depend on
 * a host's importmap.php still being able to NAME them. Everything about that
 * rests on one mechanism: the bundle registers its assets/ directory under a
 * namespace in prependExtension(), and every file inside it then has a LOGICAL
 * PATH beginning with that namespace — which is exactly what an importmap entry
 * takes as its "path".
 *
 * If that stops resolving, nothing fails loudly. The importmap simply omits the
 * entry, the browser cannot resolve the bare specifier, and every map in the
 * product is blank. So it is asserted here, against a real AssetMapper, rather
 * than trusted.
 */
final class AssetSeamTest extends KernelTestCase
{
    /**
     * The three logical paths a host's importmap.php names. Changing one of
     * these is changing the public contract of this bundle.
     *
     * @return iterable<string, array{string}>
     */
    public static function sharedModules(): iterable
    {
        yield 'uhifadhi/basemaps' => ['@uhifadhilabs/map-module/basemaps.js'];
        yield 'uhifadhi/boundary' => ['@uhifadhilabs/map-module/boundary.js'];
        yield 'uhifadhi/map-chrome' => ['@uhifadhilabs/map-module/chrome.js'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sharedModules')]
    public function testEachSharedModuleResolvesByItsLogicalPath(string $logicalPath): void
    {
        self::bootKernel();

        /** @var AssetMapperInterface $mapper */
        $mapper = self::getContainer()->get('test.asset_mapper');

        $asset = $mapper->getAsset($logicalPath);

        self::assertNotNull($asset, \sprintf('%s does not resolve — every map that imports it would be blank.', $logicalPath));
        self::assertFileExists($asset->sourcePath);
    }

    /**
     * The namespace is a published constant for the same reason the Leaflet
     * paths are: a host writes it, and a host that writes it slightly
     * differently gets silence rather than an error.
     */
    public function testTheNamespaceIsTheOneTheBundlePublishes(): void
    {
        self::assertSame('@uhifadhilabs/map-module', UhifadhiLabsMapBundle::ASSET_NAMESPACE);

        foreach (self::sharedModules() as [$logicalPath]) {
            self::assertStringStartsWith(UhifadhiLabsMapBundle::ASSET_NAMESPACE.'/', $logicalPath);
        }
    }

    /**
     * The host's <body> line, drawn for real: one Twig function, one attribute,
     * and the default deployment says esri.
     */
    public function testTheHostsBodyLineRendersTheConfiguredSource(): void
    {
        self::bootKernel();

        /** @var \Twig\Environment $twig */
        $twig = self::getContainer()->get('test.twig');

        $html = $twig->render('layout.html.twig');

        self::assertStringContainsString('data-map-satellite=', $html);
        self::assertStringContainsString('&quot;provider&quot;:&quot;esri&quot;', $html);
        self::assertStringNotContainsString('AIza', $html);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
