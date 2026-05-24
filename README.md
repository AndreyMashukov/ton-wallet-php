# ton-wallet-php

PHP implementation of the TON Wallet v4r2 contract: address derivation, seqno reading, transfer assembly with the standard `InternalMessage` envelope, and a parser for TON addresses (`UQ…` / `EQ…` user-friendly forms and the raw `workchain:hex` form).

Address derivation matches `@ton/ton`'s `WalletContractV4` byte-for-byte.

## Status

Pre-1.0. Public API may change before the 1.0 tag.

## Requirements

- PHP 8.3+
- `ext-sodium`
- `ext-gmp`

## Dependencies

- [`amashukov/ton-cell-php`](https://github.com/AndreyMashukov/ton-cell-php)
- [`amashukov/ton-crypto-php`](https://github.com/AndreyMashukov/ton-crypto-php)

## License

MIT License.
