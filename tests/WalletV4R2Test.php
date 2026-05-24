<?php

declare(strict_types=1);

namespace Amashukov\TonWallet\Tests;

use Amashukov\TonCell\AddressData;
use Amashukov\TonCell\Cell;
use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use Amashukov\TonCrypto\KeyPair;
use Amashukov\TonCrypto\Mnemonic;
use Amashukov\TonWallet\Address;
use Amashukov\TonWallet\InternalMessage;
use Amashukov\TonWallet\Tests\Stub\RecordingWalletRpc;
use Amashukov\TonWallet\WalletV4R2;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(WalletV4R2::class)]
final class WalletV4R2Test extends TestCase
{
    private const string FIXTURE_PHRASE = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon';

    private const string EXPECTED_ADDRESS_RAW = '0:4b9e4e5f0cf641f9b9a251c5cb550f5d8329d8b9afe19b52be9e13e92ebb7817';

    private const string EXPECTED_TRANSFER_HASH = 'c305ccd2ad06e66553b96f7289652a93a3473467d8e8e21059e2e98a3d003d11';

    private const string EXPECTED_TRANSFER_BOC = 'te6cckEBAgEAmAABnGgurn+tVPOj8lgteWVTSaQFoNyXv67SaaX1sHfNUaYnrfh2B/f478spP1puHt6ElqjQw8Cl8JnWSkMyLagEtw0pqaMXaBoMgAAAAAcAAQEAiWIAJc8nL4Z7IPzc0Sji5aqHrsGU7FzX8M2pX08J9JddvAugF9eEAAAAAAAAAAAAAAAAAAAAAMr+AAAAAAAAAHtDuaygCDjOmvU=';

    public function testAddressMatchesReferenceFixture(): void
    {
        $wallet = new WalletV4R2(Mnemonic::toKeyPair(self::FIXTURE_PHRASE));

        self::assertSame(self::EXPECTED_ADDRESS_RAW, $wallet->address()->toString(userFriendly: false));
    }

    public function testAddressIsNonBouncableByConvention(): void
    {
        $wallet = new WalletV4R2(Mnemonic::toKeyPair(self::FIXTURE_PHRASE));

        self::assertFalse($wallet->address()->isBounceable);
    }

    public function testCreateTransferBodyHashMatchesReferenceFixture(): void
    {
        $wallet = new WalletV4R2(Mnemonic::toKeyPair(self::FIXTURE_PHRASE));
        $body   = (new Builder())->storeUint(0xCAFE, 32)->storeUint(123, 64)->storeCoins('1000000000')->endCell();

        $transferBody = $wallet->createTransfer(
            seqno: 7,
            validUntil: 1_746_537_600,
            messages: [
                new InternalMessage(
                    dest: Address::parse('UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E'),
                    value: '50000000',
                    body: $body,
                    bounce: true,
                ),
            ],
            sendMode: 1,
        );

        self::assertSame(self::EXPECTED_TRANSFER_HASH, bin2hex($transferBody->hash()));
    }

    public function testCreateTransferBocMatchesReferenceFixture(): void
    {
        $wallet = new WalletV4R2(Mnemonic::toKeyPair(self::FIXTURE_PHRASE));
        $body   = (new Builder())->storeUint(0xCAFE, 32)->storeUint(123, 64)->storeCoins('1000000000')->endCell();

        $transferBody = $wallet->createTransfer(
            seqno: 7,
            validUntil: 1_746_537_600,
            messages: [
                new InternalMessage(
                    dest: Address::parse('UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E'),
                    value: '50000000',
                    body: $body,
                    bounce: true,
                ),
            ],
            sendMode: 1,
        );

        self::assertSame(self::EXPECTED_TRANSFER_BOC, Boc::encodeBase64($transferBody));
    }

    public function testGetSeqnoDelegatesToRpcWithRawAddress(): void
    {
        $rpc    = new RecordingWalletRpc(seqno: 42);
        $wallet = new WalletV4R2(KeyPair::fromSeed(str_repeat("\x00", 32)));

        self::assertSame(42, $wallet->getSeqno($rpc));
        self::assertSame($wallet->address()->toString(userFriendly: false), $rpc->seqnoQueriedFor);
    }

    public function testWrapExternalInMessageProducesValidEnvelope(): void
    {
        $wallet = new WalletV4R2(KeyPair::fromSeed(str_repeat("\xab", 32)));
        $body   = (new Builder())->storeUint(0xDEADBEEF, 32)->endCell();

        $slice = $wallet->wrapExternalInMessage($body)->beginParse();
        self::assertSame(2, $slice->loadUint(2));
        self::assertSame(0, $slice->loadUint(2));
        $dest = $slice->loadAddress();
        if (!$dest instanceof AddressData) {
            self::fail('external message must carry the wallet destination address');
        }
        self::assertSame($wallet->address()->hashPart, $dest->hashPart);
        self::assertSame('0', $slice->loadCoins());
        self::assertFalse($slice->loadBit());
        self::assertTrue($slice->loadBit());
        $bodyRef = $slice->loadRef();
        self::assertSame(bin2hex($body->hash()), bin2hex($bodyRef->hash()));
    }

    public function testCreateTransferRejectsEmptyMessageList(): void
    {
        $wallet = new WalletV4R2(KeyPair::fromSeed(str_repeat("\x00", 32)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects 1..4 messages');

        $wallet->createTransfer(seqno: 0, validUntil: 0, messages: []);
    }

    public function testCreateTransferRejectsMoreThanFourMessages(): void
    {
        $wallet = new WalletV4R2(KeyPair::fromSeed(str_repeat("\x00", 32)));
        $msg    = new InternalMessage(
            dest: Address::parse('UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E'),
            value: '1000',
        );

        $this->expectException(InvalidArgumentException::class);

        $wallet->createTransfer(seqno: 0, validUntil: 0, messages: [$msg, $msg, $msg, $msg, $msg]);
    }

    public function testWorkchainBeyondZeroOrMinusOneRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workchain must be 0 or -1');

        new WalletV4R2(KeyPair::fromSeed(str_repeat("\x00", 32)), workchain: 1);
    }

    public function testTransferSignatureValidatesAgainstWalletPubkey(): void
    {
        $kp     = KeyPair::generate();
        $wallet = new WalletV4R2($kp);
        $msg    = new InternalMessage(
            dest: Address::parse('UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E'),
            value: '1000',
            body: (new Builder())->storeUint(0, 32)->endCell(),
        );

        $transferBody = $wallet->createTransfer(seqno: 0, validUntil: 0, messages: [$msg]);

        $slice          = $transferBody->beginParse();
        $signature      = $slice->loadBits(512);
        $signingBuilder = (new Builder())
            ->storeUint(WalletV4R2::DEFAULT_WALLET_ID, 32)
            ->storeUint(0, 32)
            ->storeUint(0, 32)
            ->storeUint(WalletV4R2::OP_TRANSFER, 8)
            ->storeUint(3, 8)
            ->storeRef($transferBody->refs->toArray()[0]);

        self::assertTrue($kp->verify($signingBuilder->endCell()->hash(), $signature));
    }

    public function testSendTransferQueriesSeqnoThenBroadcastsBoc(): void
    {
        $rpc    = new RecordingWalletRpc(seqno: 0);
        $wallet = new WalletV4R2(KeyPair::fromSeed(str_repeat("\x05", 32)));
        $msg    = new InternalMessage(
            dest: Address::parse('UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E'),
            value: '1000',
        );

        $wallet->sendTransfer($rpc, [$msg], validUntil: 1_746_537_600, sendMode: 1);

        if (null === $rpc->sentBoc) {
            self::fail('sendTransfer must broadcast a BOC');
        }
        $decoded = base64_decode($rpc->sentBoc, true);
        if (false === $decoded) {
            self::fail('broadcast BOC must be valid base64');
        }
        self::assertGreaterThan(20, \strlen($decoded));
    }

    public function testBuildDataCellPinsSeqnoWalletIdPubkeyLayout(): void
    {
        $kp     = KeyPair::fromSeed(str_repeat("\x06", 32));
        $wallet = new WalletV4R2($kp);

        $slice = $wallet->buildDataCell(seqno: 123)->beginParse();
        self::assertSame(123, $slice->loadUint(32));
        self::assertSame(WalletV4R2::DEFAULT_WALLET_ID, $slice->loadUint(32));
        self::assertSame(bin2hex($kp->publicKey), bin2hex($slice->loadBits(256)));
        self::assertFalse($slice->loadBit());
    }

    public function testInternalMessageWithLargeBodyEmitsBodyAsRefNotInline(): void
    {
        $bigBody = (new Builder())->storeBits(str_repeat("\xAA", 113), 904)->endCell();
        $msg     = new InternalMessage(
            dest: Address::parse('UQBLnk5fDPZB-bmiUcXLVQ9dgynYua_hm1K-nhPpLrt4F82E'),
            value: '1000',
            body: $bigBody,
        );
        $wallet = new WalletV4R2(KeyPair::fromSeed(str_repeat("\x00", 32)));

        $method = new ReflectionMethod(WalletV4R2::class, 'buildInternalMessageCell');
        $cell   = $method->invoke($wallet, $msg);
        if (!$cell instanceof Cell) {
            self::fail('buildInternalMessageCell must return a Cell');
        }

        self::assertCount(1, $cell->refs);
        self::assertSame(bin2hex($bigBody->hash()), bin2hex($cell->refs->toArray()[0]->hash()));
    }
}
