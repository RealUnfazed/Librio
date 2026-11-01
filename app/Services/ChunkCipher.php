<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * File format "PFL1":
 *   header (16 bytes): "PFL1" | uint32 BE chunk size | 8-byte random nonce prefix
 *   then per chunk:    AES-256-GCM ciphertext (same length as plaintext) | 16-byte tag
 *   nonce for chunk i: nonce prefix (8) | uint32 BE i
 * Every chunk except the last holds exactly `chunk size` plaintext bytes, so any chunk can be
 * located and decrypted on its own (this is what makes Range streaming possible).
 */
class ChunkCipher
{
    public const MAGIC = 'PFL1';
    public const HEADER = 16;
    public const TAG = 16;

    public function newKey(): string
    {
        return random_bytes(32);
    }

    private function encrypter(): Encrypter
    {
        $raw = base64_decode((string) config('library.master_key'), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('LIBRARY_MASTER_KEY must be 32 random bytes, base64-encoded. Generate one with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"');
        }
        return new Encrypter($raw, 'aes-256-cbc');
    }

    public function wrap(string $key): string
    {
        return $this->encrypter()->encrypt($key, false);
    }

    public function unwrap(string $wrapped): string
    {
        return $this->encrypter()->decrypt($wrapped, false);
    }

    /** 16-byte file header: magic | chunk size | nonce prefix. */
    public function header(string $prefix, int $chunk): string
    {
        return self::MAGIC . pack('N', $chunk) . $prefix;
    }

    /**
     * Encrypts one upload piece. $plain must start on a chunk boundary of the whole file
     * ($firstIndex = index of its first chunk); only the LAST piece may end on a partial chunk.
     */
    public function encryptPiece(string $plain, string $key, string $prefix, int $firstIndex, int $chunk): string
    {
        $out = '';
        $len = strlen($plain);
        for ($off = 0, $i = $firstIndex; $off < $len; $off += $chunk, $i++) {
            $tag = '';
            $ct = openssl_encrypt(substr($plain, $off, $chunk), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $prefix . pack('N', $i), $tag, '', self::TAG);
            if ($ct === false || strlen($tag) !== self::TAG) {
                throw new RuntimeException('Encryption failed.');
            }
            $out .= $ct . $tag;
        }
        return $out;
    }

    /** Encrypts $src into $dst. Returns sizes and the nonce prefix to store with the book. */
    public function encryptFile(string $src, string $dst, string $key, ?int $chunk = null): array
    {
        $chunk ??= (int) config('library.chunk_size');
        $prefix = random_bytes(8);
        $in = fopen($src, 'rb');
        $out = fopen($dst, 'wb');
        if (! $in || ! $out) {
            throw new RuntimeException('Could not open files for encryption.');
        }

        fwrite($out, self::MAGIC . pack('N', $chunk) . $prefix);
        $index = 0;
        $plainSize = 0;

        while (($buf = self::readExactly($in, $chunk)) !== '') {
            $tag = '';
            $ct = openssl_encrypt($buf, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $prefix . pack('N', $index), $tag, '', self::TAG);
            if ($ct === false || strlen($tag) !== self::TAG) {
                throw new RuntimeException('Encryption failed.');
            }
            fwrite($out, $ct . $tag);
            $plainSize += strlen($buf);
            $index++;
        }
        fclose($in);
        fclose($out);

        if ($plainSize === 0) {
            throw new RuntimeException('The file is empty.');
        }

        return [
            'plain_size' => $plainSize,
            'enc_size' => self::HEADER + $index * self::TAG + $plainSize,
            'chunk_size' => $chunk,
            'nonce_prefix' => base64_encode($prefix),
        ];
    }

    /** $block = ciphertext + 16-byte tag for chunk number $index. */
    public function decryptChunk(string $block, string $key, string $prefix, int $index): string
    {
        $plain = openssl_decrypt(
            substr($block, 0, -self::TAG),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $prefix . pack('N', $index),
            substr($block, -self::TAG)
        );
        if ($plain === false) {
            throw new RuntimeException('A chunk failed its integrity check.');
        }
        return $plain;
    }

    /** Reads up to $n bytes from a PHP resource or a PSR-7 stream (shorter only at EOF). */
    public static function readExactly($stream, int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $want = $n - strlen($buf);
            $part = is_resource($stream) ? fread($stream, $want) : $stream->read($want);
            if ($part === '' || $part === false) {
                break;
            }
            $buf .= $part;
        }
        return $buf;
    }

    /** Throws away $n bytes from a PSR-7 stream (used when the CDN ignores Range). */
    public static function discard($stream, int $n): void
    {
        while ($n > 0) {
            $got = $stream->read(min($n, 65536));
            if ($got === '') {
                return;
            }
            $n -= strlen($got);
        }
    }
}
