<?php

declare(strict_types=1);

namespace Amashukov\TonWallet\Tests;

use Amashukov\TonCell\AddressData;
use Amashukov\TonWallet\Address;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    private const string USER_FRIENDLY = 'UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E';

    private const string RAW = '0:4b9e4e5f0cf641f9b9a251c5cb550f5d8329d8b9afe19b52be9e13e92ebb7817';

    public function testParseRawForm(): void
    {
        $addr = Address::parse(self::RAW);

        self::assertSame(0, $addr->wc);
        self::assertSame('4b9e4e5f0cf641f9b9a251c5cb550f5d8329d8b9afe19b52be9e13e92ebb7817', bin2hex($addr->hashPart));
        self::assertFalse($addr->isUserFriendly);
    }

    public function testParseUserFriendlyForm(): void
    {
        $addr = Address::parse(self::USER_FRIENDLY);

        self::assertSame(0, $addr->wc);
        self::assertFalse($addr->isBounceable, 'UQ prefix is the non-bounceable tag');
        self::assertTrue($addr->isUrlSafe);
    }

    public function testRawAndUserFriendlyShareHashPart(): void
    {
        $raw = Address::parse(self::RAW);
        $uf  = Address::parse(self::USER_FRIENDLY);

        self::assertSame(bin2hex($raw->hashPart), bin2hex($uf->hashPart));
    }

    public function testRawRoundtrip(): void
    {
        self::assertSame(self::RAW, Address::parse(self::RAW)->toString(userFriendly: false));
    }

    public function testUserFriendlyRoundtrip(): void
    {
        self::assertSame(self::USER_FRIENDLY, Address::parse(self::USER_FRIENDLY)->toString());
    }

    public function testParseAcceptsAddressInstanceAndClones(): void
    {
        $original = Address::parse(self::RAW);
        $cloned   = Address::parse($original);

        self::assertNotSame($original, $cloned);
        self::assertSame($original->hashPart, $cloned->hashPart);
    }

    public function testBounceableSerialisationMatchesEqPrefix(): void
    {
        $addr = Address::parse(self::RAW);

        self::assertStringStartsWith('EQ', $addr->toString(userFriendly: true, urlSafe: true, bounceable: true));
        self::assertStringStartsWith('UQ', $addr->toString(userFriendly: true, urlSafe: true, bounceable: false));
    }

    public function testToCellDataExposesWcAndHashPart(): void
    {
        $addr = Address::parse(self::RAW);
        $data = $addr->toCellData();

        self::assertInstanceOf(AddressData::class, $data);
        self::assertSame(0, $data->wc);
        self::assertSame($addr->hashPart, $data->hashPart);
    }

    public function testIsValidTrueForValidAddress(): void
    {
        self::assertTrue(Address::isValid(self::RAW));
        self::assertTrue(Address::isValid(self::USER_FRIENDLY));
    }

    public function testIsValidFalseForGarbage(): void
    {
        self::assertFalse(Address::isValid('not-an-address'));
    }

    public function testConstructorRejectsBadWorkchain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workchain must be 0 or -1');

        new Address(wc: 5, hashPart: str_repeat("\x00", 32));
    }

    public function testConstructorRejectsBadHashPartLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('32-byte');

        new Address(wc: 0, hashPart: str_repeat("\x00", 31));
    }

    public function testParseRejectsWrongCrc(): void
    {
        $tampered = substr(self::USER_FRIENDLY, 0, -2) . 'XY';

        $this->expectException(InvalidArgumentException::class);

        Address::parse($tampered);
    }

    public function testParseRejectsUnknownFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid address format');

        Address::parse('0:short');
    }
}
