<?php
declare(strict_types=1);

/**
 * GpsLab component.
 *
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 * @license http://opensource.org/licenses/MIT
 */

namespace GpsLab\Component\Sitemap\Writer;

use GpsLab\Component\Sitemap\Writer\Exception\CompressionEncodingException;
use GpsLab\Component\Sitemap\Writer\Exception\CompressionLevelException;
use GpsLab\Component\Sitemap\Writer\Exception\CompressionMemoryException;
use GpsLab\Component\Sitemap\Writer\Exception\CompressionWindowException;
use GpsLab\Component\Sitemap\Writer\Exception\DeflateCompressionException;
use GpsLab\Component\Sitemap\Writer\Exception\ExtensionNotLoadedException;
use GpsLab\Component\Sitemap\Writer\Exception\FileAccessException;
use GpsLab\Component\Sitemap\Writer\State\Exception\WriterStateException;
use GpsLab\Component\Sitemap\Writer\State\WriterState;

final class DeflateFileWriter implements Writer
{
    /**
     * @var resource|null
     */
    private $handle;

    /**
     * @var resource|null
     */
    private $context;

    /**
     * @var int
     */
    private $encoding;

    /**
     * @var int
     */
    private $level;

    /**
     * @var int
     */
    private $memory;

    /**
     * @var int
     */
    private $window;

    /**
     * @var WriterState
     */
    private $state;

    /**
     * @param int $encoding
     * @param int $level
     * @param int $memory
     * @param int $window
     */
    public function __construct(
        int $encoding = ZLIB_ENCODING_GZIP,
        int $level = -1,
        int $memory = 9,
        int $window = 15
    ) {
        if (!extension_loaded('zlib')) {
            throw ExtensionNotLoadedException::zlib();
        }

        if (!in_array($encoding, [ZLIB_ENCODING_RAW, ZLIB_ENCODING_GZIP, ZLIB_ENCODING_DEFLATE], true)) {
            throw CompressionEncodingException::invalid($encoding);
        }

        if ($level < -1 || $level > 9) {
            throw CompressionLevelException::invalid($level, -1, 9);
        }

        if ($memory < 1 || $memory > 9) {
            throw CompressionMemoryException::invalid($memory, 1, 9);
        }

        if ($window < 8 || $window > 15) {
            throw CompressionWindowException::invalid($window, 8, 15);
        }

        $this->encoding = $encoding;
        $this->level = $level;
        $this->memory = $memory;
        $this->window = $window;
        $this->state = new WriterState();
    }

    /**
     * @param string $filename
     */
    public function start(string $filename): void
    {
        $handle = fopen($filename, 'wb');

        if ($handle === false) {
            throw FileAccessException::notWritable($filename);
        }

        $context = deflate_init($this->encoding, [
            'level' => $this->level,
            'memory' => $this->memory,
            'window' => $this->window,
        ]);

        if ($context === false) {
            throw DeflateCompressionException::failedInit();
        }

        $this->state->start();
        $this->handle = $handle;
        $this->context = $context;
    }

    /**
     * @param string $content
     */
    public function append(string $content): void
    {
        if (!$this->state->isReady()) {
            throw WriterStateException::notReady();
        }

        $data = deflate_add($this->context, $content, ZLIB_NO_FLUSH);

        if ($data === false) {
            throw DeflateCompressionException::failedAdd($content);
        }

        fwrite($this->handle, $data);
    }

    public function finish(): void
    {
        $this->state->finish();

        $data = deflate_add($this->context, '', ZLIB_FINISH);

        if ($data === false) {
            throw DeflateCompressionException::failedFinish();
        }

        fwrite($this->handle, $data);
        fclose($this->handle);

        $this->handle = null;
        $this->context = null;
    }
}
