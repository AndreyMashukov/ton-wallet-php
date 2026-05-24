<?php

declare(strict_types=1);

namespace Amashukov\TonWallet;

interface WalletRpcInterface
{
    /**
     * Current seqno of the wallet contract at the given raw address
     * (`wc:hex`), or 0 when the wallet is not yet deployed.
     */
    public function getSeqno(string $rawAddress): int;

    /**
     * Broadcast a base64-encoded BOC (external-in message) to the network.
     */
    public function sendBoc(string $base64Boc): void;
}
