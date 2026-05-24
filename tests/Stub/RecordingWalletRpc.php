<?php

declare(strict_types=1);

namespace Amashukov\TonWallet\Tests\Stub;

use Amashukov\TonWallet\WalletRpcInterface;

final class RecordingWalletRpc implements WalletRpcInterface
{
    public ?string $sentBoc = null;

    public ?string $seqnoQueriedFor = null;

    public function __construct(private readonly int $seqno) {}

    public function getSeqno(string $rawAddress): int
    {
        $this->seqnoQueriedFor = $rawAddress;

        return $this->seqno;
    }

    public function sendBoc(string $base64Boc): void
    {
        $this->sentBoc = $base64Boc;
    }
}
