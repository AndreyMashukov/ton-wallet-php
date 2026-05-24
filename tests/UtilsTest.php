<?php

declare(strict_types=1);

namespace Amashukov\TonWallet\Tests;

use Amashukov\TonWallet\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Utils::class)]
final class UtilsTest extends TestCase
{
    public function testBase64RoundtripUrlSafe(): void
    {
        $data    = random_bytes(36);
        $encoded = Utils::base64encode($data, true);

        self::assertStringNotContainsString('+', $encoded);
        self::assertStringNotContainsString('/', $encoded);
        self::assertSame($data, Utils::base64decode($encoded));
    }

    public function testBase64RoundtripStandard(): void
    {
        $data    = random_bytes(36);
        $encoded = Utils::base64encode($data, false);

        self::assertSame($data, Utils::base64decode($encoded));
    }

    public function testBase64DecodeRejectsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Utils::base64decode('not valid base64 !!!@@@');
    }

    public function testCrc16ProducesTwoBytes(): void
    {
        self::assertSame(2, \strlen(Utils::crc16('hello')));
    }

    public function testCrc16IsDeterministic(): void
    {
        self::assertSame(Utils::crc16('payload'), Utils::crc16('payload'));
    }

    public function testToNanoConvertsWholeTon(): void
    {
        self::assertSame('1000000000', Utils::toNano('1'));
        self::assertSame('500000000', Utils::toNano('0.5'));
    }

    public function testToNanoHandlesValuesBeyondPhpIntMax(): void
    {
        self::assertSame('10000000000000000000', Utils::toNano('10000000000'));
    }

    public function testToNanoRejectsNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('numeric TON string');

        Utils::toNano('abc');
    }

    public function testToTonConvertsNanoToDecimalString(): void
    {
        self::assertSame('1.000000000', Utils::toTon(1_000_000_000));
        self::assertSame('0.500000000', Utils::toTon(500_000_000));
    }

    public function testToTonHandlesBigintNanoString(): void
    {
        self::assertSame('10.000000000', Utils::toTon('10000000000'));
    }

    public function testSignedHexAbsConvertsHighByteToSignedInt8(): void
    {
        self::assertSame(-1, Utils::signedHexAbs(0xFF));
        self::assertSame(17, Utils::signedHexAbs(0x11));
    }
}
