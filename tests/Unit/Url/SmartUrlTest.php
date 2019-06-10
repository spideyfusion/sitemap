<?php
declare(strict_types=1);

/**
 * GpsLab component.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/MIT
 */

namespace GpsLab\Component\Sitemap\Tests\Unit\Url;

use GpsLab\Component\Sitemap\Url\SmartUrl;
use PHPUnit\Framework\TestCase;

class SmartUrlTest extends TestCase
{
    public function testDefaultUrl(): void
    {
        $loc = '';
        $url = new SmartUrl($loc);

        self::assertEquals($loc, $url->getLoc());
        self::assertInstanceOf(\DateTimeImmutable::class, $url->getLastMod());
        self::assertEquals(SmartUrl::CHANGE_FREQ_HOURLY, $url->getChangeFreq());
        self::assertEquals(SmartUrl::DEFAULT_PRIORITY, $url->getPriority());
    }

    /**
     * @return array
     */
    public function urls(): array
    {
        return [
            [new \DateTimeImmutable('-10 minutes'), SmartUrl::CHANGE_FREQ_ALWAYS, '1.0'],
            [new \DateTimeImmutable('-1 hour'), SmartUrl::CHANGE_FREQ_HOURLY, '1.0'],
            [new \DateTimeImmutable('-1 day'), SmartUrl::CHANGE_FREQ_DAILY, '0.9'],
            [new \DateTimeImmutable('-1 week'), SmartUrl::CHANGE_FREQ_WEEKLY, '0.5'],
            [new \DateTimeImmutable('-1 month'), SmartUrl::CHANGE_FREQ_MONTHLY, '0.2'],
            [new \DateTimeImmutable('-1 year'), SmartUrl::CHANGE_FREQ_YEARLY, '0.1'],
            [new \DateTimeImmutable('-2 year'), SmartUrl::CHANGE_FREQ_NEVER, '0.0'],
        ];
    }

    /**
     * @dataProvider urls
     *
     * @param \DateTimeImmutable $last_mod
     * @param string             $change_freq
     * @param string             $priority
     */
    public function testCustomUrl(\DateTimeImmutable $last_mod, string $change_freq, string $priority): void
    {
        $loc = '/';

        $url = new SmartUrl($loc, $last_mod, $change_freq, $priority);

        self::assertEquals($loc, $url->getLoc());
        self::assertEquals($last_mod, $url->getLastMod());
        self::assertEquals($change_freq, $url->getChangeFreq());
        self::assertEquals($priority, $url->getPriority());
    }

    /**
     * @return array
     */
    public function priorityOfLocations(): array
    {
        return [
            ['/', '1.0'],
            ['/index.html', '0.9'],
            ['/catalog', '0.9'],
            ['/catalog/123', '0.8'],
            ['/catalog/123/article', '0.7'],
            ['/catalog/123/article/456', '0.6'],
            ['/catalog/123/article/456/print', '0.5'],
            ['/catalog/123/subcatalog/789/article/456', '0.4'],
            ['/catalog/123/subcatalog/789/article/456/print', '0.3'],
            ['/catalog/123/subcatalog/789/article/456/print/foo', '0.2'],
            ['/catalog/123/subcatalog/789/article/456/print/foo/bar', '0.1'],
            ['/catalog/123/subcatalog/789/article/456/print/foo/bar/baz', '0.1'],
            ['/catalog/123/subcatalog/789/article/456/print/foo/bar/baz/qux', '0.1'],
        ];
    }

    /**
     * @dataProvider priorityOfLocations
     *
     * @param string $loc
     * @param string $priority
     */
    public function testSmartPriority(string $loc, string $priority): void
    {
        $url = new SmartUrl($loc);

        self::assertEquals($loc, $url->getLoc());
        self::assertEquals($priority, $url->getPriority());
    }

    /**
     * @return array
     */
    public function changeFreqOfLastMod(): array
    {
        return [
            [new \DateTimeImmutable('-1 year -1 day'), SmartUrl::CHANGE_FREQ_YEARLY],
            [new \DateTimeImmutable('-1 month -1 day'), SmartUrl::CHANGE_FREQ_MONTHLY],
            [new \DateTimeImmutable('-10 minutes'), SmartUrl::CHANGE_FREQ_HOURLY],
        ];
    }

    /**
     * @dataProvider changeFreqOfLastMod
     *
     * @param \DateTimeImmutable $last_mod
     * @param string             $change_freq
     */
    public function testSmartChangeFreqFromLastMod(\DateTimeImmutable $last_mod, string $change_freq): void
    {
        $loc = '/';
        $url = new SmartUrl($loc, $last_mod);

        self::assertEquals($loc, $url->getLoc());
        self::assertEquals($last_mod, $url->getLastMod());
        self::assertEquals($change_freq, $url->getChangeFreq());
    }

    /**
     * @return array
     */
    public function changeFreqOfPriority(): array
    {
        return [
            ['1.0', SmartUrl::CHANGE_FREQ_HOURLY],
            ['0.9', SmartUrl::CHANGE_FREQ_DAILY],
            ['0.8', SmartUrl::CHANGE_FREQ_DAILY],
            ['0.7', SmartUrl::CHANGE_FREQ_WEEKLY],
            ['0.6', SmartUrl::CHANGE_FREQ_WEEKLY],
            ['0.5', SmartUrl::CHANGE_FREQ_WEEKLY],
            ['0.4', SmartUrl::CHANGE_FREQ_MONTHLY],
            ['0.3', SmartUrl::CHANGE_FREQ_MONTHLY],
            ['0.2', SmartUrl::CHANGE_FREQ_YEARLY],
            ['0.1', SmartUrl::CHANGE_FREQ_YEARLY],
            ['0.0', SmartUrl::CHANGE_FREQ_NEVER],
            ['-', SmartUrl::DEFAULT_CHANGE_FREQ],
        ];
    }

    /**
     * @dataProvider changeFreqOfPriority
     *
     * @param string $priority
     * @param string $change_freq
     */
    public function testSmartChangeFreqFromPriority(string $priority, string $change_freq): void
    {
        $loc = '/';
        $url = new SmartUrl($loc, null, null, $priority);

        self::assertEquals($loc, $url->getLoc());
        self::assertInstanceOf(\DateTimeImmutable::class, $url->getLastMod());
        self::assertEquals($change_freq, $url->getChangeFreq());
        self::assertEquals($priority, $url->getPriority());
    }
}
