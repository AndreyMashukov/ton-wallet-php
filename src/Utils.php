<?php

declare(strict_types=1);

namespace Amashukov\TonWallet;

use InvalidArgumentException;

final readonly class Utils
{
    public static function base64encode(string $data, bool $urlSafe = true): string
    {
        $encoded = base64_encode($data);
        if ($urlSafe) {
            return strtr($encoded, '/+', '_-');
        }

        return $encoded;
    }

    public static function base64decode(string $input): string
    {
        $decoded = base64_decode(strtr($input, '_-', '/+'), true);
        if (false === $decoded) {
            throw new InvalidArgumentException('Utils::base64decode received invalid base64 input');
        }

        return $decoded;
    }

    public static function signedHexAbs(int $input): int
    {
        $unpacked = unpack('c*', pack('C*', $input));
        if (false === $unpacked) {
            throw new InvalidArgumentException('Utils::signedHexAbs failed to unpack byte');
        }

        return (int) $unpacked[1];
    }

    public static function crc16(string $data): string
    {
        $poly = 0x1021;

        $unpacked = unpack('C*', $data);
        if (false === $unpacked) {
            throw new InvalidArgumentException('Utils::crc16 failed to unpack input bytes');
        }
        $bytes = [...array_values($unpacked), 0, 0];
        $reg   = 0;

        foreach ($bytes as $byte) {
            $mask = 0x80;
            while ($mask > 0) {
                $reg <<= 1;
                if (($byte & $mask) !== 0) {
                    ++$reg;
                }
                $mask >>= 1;
                if ($reg > 0xFFFF) {
                    $reg &= 0xFFFF;
                    $reg ^= $poly;
                }
            }
        }

        return pack('CC', (int) floor($reg / 256), $reg % 256);
    }

    public static function toTon(int|string $nano): string
    {
        return bcdiv((string) $nano, '1000000000', 9);
    }

    public static function toNano(string $ton): string
    {
        if (!is_numeric($ton)) {
            throw new InvalidArgumentException(sprintf('toNano expects a numeric TON string, got "%s"', $ton));
        }

        return bcmul($ton, '1000000000', 0);
    }
}
