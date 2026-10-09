<?php

declare(strict_types=1);

namespace Amashukov\TonWallet;

use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use Amashukov\TonCell\Cell;
use Amashukov\TonCrypto\KeyPair;
use InvalidArgumentException;

final readonly class WalletV4R2
{
    public const string CODE_HASH_HEX = 'feb5ff6820e2ff0d9483e7e0d62c817d846789fb4ae580c878866d959dabd5c0';

    public const int CODE_MAX_DEPTH = 7;

    public const string CODE_BOC = 'te6ccgECFAEAAtQAART/APSkE/S88sgLAQIBIAIDAgFIBAUE+PKDCNcYINMf0x/THwL4I7vyZO1E0NMf0x/T//QE0VFDuvKhUVG68qIF+QFUEGT5EPKj+AAkpMjLH1JAyx9SMMv/UhD0AMntVPgPAdMHIcAAn2xRkyDXSpbTB9QC+wDoMOAhwAHjACHAAuMAAcADkTDjDQOkyMsfEssfy/8QERITAubQAdDTAyFxsJJfBOAi10nBIJJfBOAC0x8hghBwbHVnvSKCEGRzdHK9sJJfBeAD+kAwIPpEAcjKB8v/ydDtRNCBAUDXIfQEMFyBAQj0Cm+hMbOSXwfgBdM/yCWCEHBsdWe6kjgw4w0DghBkc3RyupJfBuMNBgcCASAICQB4AfoA9AQw+CdvIjBQCqEhvvLgUIIQcGx1Z4MesXCAGFAEywUmzxZY+gIZ9ADLaRfLH1Jgyz8gyYBA+wAGAIpQBIEBCPRZMO1E0IEBQNcgyAHPFvQAye1UAXKwjiOCEGRzdHKDHrFwgBhQBcsFUAPPFiP6AhPLassfyz/JgED7AJJfA+ICASAKCwBZvSQrb2omhAgKBrkPoCGEcNQICEekk30pkQzmkD6f+YN4EoAbeBAUiYcVnzGEAgFYDA0AEbjJftRNDXCx+AA9sp37UTQgQFA1yH0BDACyMoHy//J0AGBAQj0Cm+hMYAIBIA4PABmtznaiaEAga5Drhf/AABmvHfaiaEAQa5DrhY/AAG7SB/oA1NQi+QAFyMoHFcv/ydB3dIAYyMsFywIizxZQBfoCFMtrEszMyXP7AMhAFIEBCPRR8qcCAHCBAQjXGPoA0z/IVCBHgQEI9FHyp4IQbm90ZXB0gBjIywXLAlAGzxZQBPoCFMtqEssfyz/Jc/sAAgBsgQEI1xj6ANM/MFIkgQEI9Fnyp4IQZHN0cnB0gBjIywXLAlAFzxZQA/oCE8tqyx8Syz/Jc/sAAAr0AMntVA==';

    public const int DEFAULT_WALLET_ID = 698_983_191;

    public const int OP_TRANSFER = 0;

    public function __construct(
        public KeyPair $keys,
        public int $walletId = self::DEFAULT_WALLET_ID,
        public int $workchain = 0,
    ) {
        if (0 !== $workchain && -1 !== $workchain) {
            throw new InvalidArgumentException(sprintf('Workchain must be 0 or -1, got %d', $workchain));
        }
    }

    public function address(): Address
    {
        $hash = $this->stateInitHash($this->buildDataCell(0));

        return new Address(
            wc: $this->workchain,
            hashPart: $hash,
            isBounceable: false,
            isUserFriendly: true,
        );
    }

    public static function code(): Cell
    {
        return Boc::decodeBase64(self::CODE_BOC);
    }

    public function stateInit(): Cell
    {
        return (new Builder())
            ->storeBit(false)
            ->storeBit(false)
            ->storeBit(true)
            ->storeBit(true)
            ->storeBit(false)
            ->storeRef(self::code())
            ->storeRef($this->buildDataCell(0))
            ->endCell();
    }

    public function getSeqno(WalletRpcInterface $rpc): int
    {
        return $rpc->getSeqno($this->address()->toString(userFriendly: false));
    }

    /**
     * @param list<InternalMessage> $messages 1..4 outgoing messages
     */
    public function createTransfer(
        int $seqno,
        int $validUntil,
        array $messages,
        int $sendMode = 3,
    ): Cell {
        if ([] === $messages || \count($messages) > 4) {
            throw new InvalidArgumentException(sprintf('createTransfer expects 1..4 messages, got %d', \count($messages)));
        }

        $signingMessage = (new Builder())
            ->storeUint($this->walletId, 32)
            ->storeUint($validUntil, 32)
            ->storeUint($seqno, 32)
            ->storeUint(self::OP_TRANSFER, 8);

        foreach ($messages as $msg) {
            $signingMessage
                ->storeUint($sendMode, 8)
                ->storeRef($this->buildInternalMessageCell($msg));
        }

        $signature = $this->keys->sign($signingMessage->endCell()->hash());

        return (new Builder())
            ->storeBuffer($signature)
            ->storeBuilder($signingMessage)
            ->endCell();
    }

    /**
     * @param list<InternalMessage> $messages
     */
    public function sendTransfer(
        WalletRpcInterface $rpc,
        array $messages,
        int $validUntil,
        int $sendMode = 3,
        bool $deployIfNeeded = false,
    ): void {
        $seqno = $this->getSeqno($rpc);
        $body  = $this->createTransfer($seqno, $validUntil, $messages, $sendMode);
        $ext   = $this->wrapExternalInMessage($body, withStateInit: $deployIfNeeded && 0 === $seqno);

        $rpc->sendBoc(Boc::encodeBase64($ext));
    }

    public function wrapExternalInMessage(Cell $body, bool $withStateInit = false): Cell
    {
        $message = (new Builder())
            ->storeUint(2, 2)
            ->storeUint(0, 2)
            ->storeAddress($this->address()->toCellData())
            ->storeCoins(0);

        if ($withStateInit) {
            $message->storeBit(true)->storeBit(true)->storeRef($this->stateInit());
        } else {
            $message->storeBit(false);
        }

        return $message
            ->storeBit(true)
            ->storeRef($body)
            ->endCell();
    }

    public function buildDataCell(int $seqno): Cell
    {
        return (new Builder())
            ->storeUint($seqno, 32)
            ->storeUint($this->walletId, 32)
            ->storeBuffer($this->keys->publicKey)
            ->storeBit(false)
            ->endCell();
    }

    private function stateInitHash(Cell $dataCell): string
    {
        $codeHash = hex2bin(self::CODE_HASH_HEX);
        if (false === $codeHash) {
            throw new InvalidArgumentException('WalletV4R2: CODE_HASH_HEX is not valid hex');
        }

        $repr = "\x02\x01\x34"
            . pack('n', self::CODE_MAX_DEPTH)
            . pack('n', $dataCell->maxDepth())
            . $codeHash
            . $dataCell->hash();

        return hash('sha256', $repr, true);
    }

    private function buildInternalMessageCell(InternalMessage $msg): Cell
    {
        $b = (new Builder())
            ->storeBit(false)
            ->storeBit(true)
            ->storeBit($msg->bounce)
            ->storeBit(false)
            ->storeUint(0, 2)
            ->storeAddress($msg->dest->toCellData())
            ->storeCoins($msg->value)
            ->storeBit(false)
            ->storeCoins(0)
            ->storeCoins(0)
            ->storeUint(0, 64)
            ->storeUint(0, 32)
            ->storeBit(false);
        if (!$msg->body instanceof Cell) {
            $b->storeBit(false);
        } elseif (
            $b->bits()    + 1 + $msg->body->bitLength <= Cell::MAX_BITS
            && $b->refs() + \count($msg->body->refs)   <= Cell::MAX_REFS
        ) {
            $b->storeBit(false);
            $b->storeCellInline($msg->body);
        } else {
            $b->storeBit(true);
            $b->storeRef($msg->body);
        }

        return $b->endCell();
    }
}
