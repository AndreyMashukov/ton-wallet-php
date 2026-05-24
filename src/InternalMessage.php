<?php

declare(strict_types=1);

namespace Amashukov\TonWallet;

use Amashukov\TonCell\Cell;

final readonly class InternalMessage
{
    public function __construct(
        public Address $dest,
        public string $value,
        public ?Cell $body = null,
        public bool $bounce = true,
    ) {}
}
