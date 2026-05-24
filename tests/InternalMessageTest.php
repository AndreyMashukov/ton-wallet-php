<?php

declare(strict_types=1);

namespace Amashukov\TonWallet\Tests;

use Amashukov\TonCell\Builder;
use Amashukov\TonWallet\Address;
use Amashukov\TonWallet\InternalMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InternalMessage::class)]
final class InternalMessageTest extends TestCase
{
    public function testStoresFieldsVerbatim(): void
    {
        $dest = Address::parse('0:4b9e4e5f0cf641f9b9a251c5cb550f5d8329d8b9afe19b52be9e13e92ebb7817');
        $body = (new Builder())->storeUint(1, 8)->endCell();
        $msg  = new InternalMessage($dest, '50000000', $body, false);

        self::assertSame($dest, $msg->dest);
        self::assertSame('50000000', $msg->value);
        self::assertSame($body, $msg->body);
        self::assertFalse($msg->bounce);
    }

    public function testDefaultsBodyNullAndBounceTrue(): void
    {
        $dest = Address::parse('0:4b9e4e5f0cf641f9b9a251c5cb550f5d8329d8b9afe19b52be9e13e92ebb7817');
        $msg  = new InternalMessage($dest, '1000');

        self::assertNull($msg->body);
        self::assertTrue($msg->bounce);
    }
}
