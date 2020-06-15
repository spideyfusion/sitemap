<?php
declare(strict_types=1);

/**
 * GpsLab component.
 *
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 * @license http://opensource.org/licenses/MIT
 */

namespace GpsLab\Component\Sitemap\Tests\Url;

use GpsLab\Component\Sitemap\Url\ChangeFrequency;
use GpsLab\Component\Sitemap\Url\Exception\InvalidChangeFrequencyException;
use GpsLab\Component\Sitemap\Url\Exception\InvalidLastModifyException;
use GpsLab\Component\Sitemap\Url\Exception\InvalidLocationException;
use GpsLab\Component\Sitemap\Url\Exception\InvalidPriorityException;
use GpsLab\Component\Sitemap\Url\Language;
use GpsLab\Component\Sitemap\Url\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testDefaultUrl(): void
    {
        $location = '';
        $url = new Url($location);

        self::assertEquals($location, $url->getLocation());
        self::assertNull($url->getLastModify());
        self::assertNull($url->getChangeFrequency());
        self::assertNull($url->getPriority());
        self::assertEmpty($url->getLanguages());
    }

    /**
     * @return array<int, array<int, \DateTimeInterface|string|int>>
     */
    public function getUrls(): array
    {
        return [
            [new \DateTimeImmutable('-10 minutes'), ChangeFrequency::ALWAYS, 10],
            [new \DateTimeImmutable('-1 hour'), ChangeFrequency::HOURLY, 10],
            [new \DateTimeImmutable('-1 day'), ChangeFrequency::DAILY, 9],
            [new \DateTimeImmutable('-1 week'), ChangeFrequency::WEEKLY, 5],
            [new \DateTimeImmutable('-1 month'), ChangeFrequency::MONTHLY, 2],
            [new \DateTimeImmutable('-1 year'), ChangeFrequency::YEARLY, 1],
            [new \DateTimeImmutable('-2 year'), ChangeFrequency::NEVER, 0],
            [new \DateTime('-10 minutes'), ChangeFrequency::ALWAYS, 10],
            [new \DateTime('-1 hour'), ChangeFrequency::HOURLY, 10],
            [new \DateTime('-1 day'), ChangeFrequency::DAILY, 9],
            [new \DateTime('-1 week'), ChangeFrequency::WEEKLY, 5],
            [new \DateTime('-1 month'), ChangeFrequency::MONTHLY, 2],
            [new \DateTime('-1 year'), ChangeFrequency::YEARLY, 1],
            [new \DateTime('-2 year'), ChangeFrequency::NEVER, 0],
        ];
    }

    /**
     * @dataProvider getUrls
     *
     * @param \DateTimeInterface $last_modify
     * @param string             $change_frequency
     * @param int                $priority
     */
    public function testCustomUrl(\DateTimeInterface $last_modify, string $change_frequency, int $priority): void
    {
        $location = '/index.html';

        $url = new Url($location, $last_modify, $change_frequency, $priority);

        self::assertEquals($location, $url->getLocation());
        self::assertEquals($last_modify, $url->getLastModify());
        self::assertEquals($change_frequency, $url->getChangeFrequency());
        self::assertEquals($priority, $url->getPriority());
    }

    /**
     * @return string[][]
     */
    public function getInvalidLocations(): array
    {
        return [
            ['../'],
            ['index.html'],
            ['&foo=bar'],
            ['№'],
            ['@'],
            ['\\'],
        ];
    }

    /**
     * @dataProvider getInvalidLocations
     *
     * @param string $location
     */
    public function testInvalidLocation(string $location): void
    {
        $this->expectException(InvalidLocationException::class);

        new Url($location);
    }

    /**
     * @return string[][]
     */
    public function getValidLocations(): array
    {
        return [
            [''],
            ['/'],
            ['#about'],
            ['?foo=bar'],
            ['?foo=bar&baz=123'],
            ['/index.html'],
            ['/about/index.html'],
        ];
    }

    /**
     * @dataProvider getValidLocations
     *
     * @param string $location
     */
    public function testValidLocation(string $location): void
    {
        $this->assertEquals($location, (new Url($location))->getLocation());
    }

    public function testInvalidLastModify(): void
    {
        $this->expectException(InvalidLastModifyException::class);

        new Url('/', new \DateTimeImmutable('+1 minutes'));
    }

    public function testInvalidPriority(): void
    {
        $this->expectException(InvalidPriorityException::class);

        new Url('/', null, null, 11);
    }

    public function testInvalidChangeFrequency(): void
    {
        $this->expectException(InvalidChangeFrequencyException::class);

        new Url('/', null, '');
    }

    public function testGetLanguages(): void
    {
        $languages = [
            'de' => '/deutsch/page.html',
            'de-ch' => '/schweiz-deutsch/page.html',
            'en' => '/english/page.html',
        ];

        $url = new Url('/english/page.html', null, null, null, $languages);

        self::assertNotEmpty($url->getLanguages());

        $keys = array_keys($languages);

        foreach ($url->getLanguages() as $j => $language) {
            self::assertInstanceOf(Language::class, $language);
            self::assertSame($keys[$j], $language->getLanguage());
            self::assertSame($languages[$keys[$j]], $language->getLocation());
        }
    }

    /**
     * @dataProvider getUrls
     *
     * @param \DateTimeInterface $last_modify
     * @param string             $change_frequency
     * @param int                $priority
     */
    public function testCreateLanguageUrls(
        \DateTimeInterface $last_modify,
        string $change_frequency,
        int $priority
    ): void {
        $languages = [
            'de' => '/deutsch/page.html',
            'de-ch' => '/schweiz-deutsch/page.html',
            'en' => '/english/page.html',
        ];
        $external_languages = [
            'de' => 'https://example.de', // should be overwritten from $languages
            'fr' => 'https://example.fr',
        ];
        $expected_locations = array_values($languages);
        $expected_languages = array_replace($external_languages, $languages);

        $urls = Url::createLanguageUrls($languages, $last_modify, $change_frequency, $priority, $external_languages);

        self::assertNotEmpty($urls);

        foreach ($urls as $i => $url) {
            self::assertSame($last_modify, $url->getLastModify());
            self::assertSame($change_frequency, $url->getChangeFrequency());
            self::assertSame($priority, $url->getPriority());
            self::assertSame($expected_locations[$i], $url->getLocation());
            self::assertNotEmpty($url->getLanguages());

            $keys = array_keys($expected_languages);
            foreach ($url->getLanguages() as $j => $language) {
                self::assertInstanceOf(Language::class, $language);
                self::assertSame($keys[$j], $language->getLanguage());
                self::assertSame($expected_languages[$keys[$j]], $language->getLocation());
            }
        }
    }
}
