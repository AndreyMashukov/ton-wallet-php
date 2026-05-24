# amashukov/ton-wallet-php

Pure-PHP TON Wallet v4r2 — address derivation, transfer assembly, and a TON address parser for The Open Network.

[![CI](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/ton-wallet-php/ci.yml?branch=main&label=CI)](https://github.com/AndreyMashukov/ton-wallet-php/actions)
[![PHPStan L9](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/ton-wallet-php/stan.yml?branch=main&label=PHPStan%20L9)](https://github.com/AndreyMashukov/ton-wallet-php/actions)
[![Latest Version](https://img.shields.io/packagist/v/amashukov/ton-wallet-php)](https://packagist.org/packages/amashukov/ton-wallet-php)
[![Downloads](https://img.shields.io/packagist/dt/amashukov/ton-wallet-php)](https://packagist.org/packages/amashukov/ton-wallet-php)
[![PHP](https://img.shields.io/packagist/dependency-v/amashukov/ton-wallet-php/php)](https://packagist.org/packages/amashukov/ton-wallet-php)
[![License](https://img.shields.io/packagist/l/amashukov/ton-wallet-php)](LICENSE)
[![Stars](https://img.shields.io/github/stars/AndreyMashukov/ton-wallet-php?style=social)](https://github.com/AndreyMashukov/ton-wallet-php)

A pure-PHP implementation of the **TON Wallet v4r2 contract** for [The Open Network (TON)](https://ton.org): derive the wallet address, assemble signed transfers with the standard `InternalMessage` envelope, and parse / serialize TON addresses in every form. Address derivation matches `@ton/ton`'s `WalletContractV4` byte-for-byte, so a wallet built here lands at the same on-chain address as the JavaScript SDK. Broadcasting is decoupled behind a tiny RPC interface, so the same wallet object signs offline and ships through any backend.

## Features

- **Wallet v4r2 address derivation** — `WalletV4R2::address()` reproduces the exact state-init hash `@ton/ton` computes; same keys, same address.
- **Offline transfer assembly** — `createTransfer()` builds and signs the external-in message body without any network I/O; broadcast is a separate step.
- **1..4 messages per transfer** — batch up to four outgoing `InternalMessage`s in a single signed external message, with a per-message `sendMode`.
- **Full TON address parser** — `Address::parse()` handles user-friendly `UQ…` / `EQ…` (bounceable + non-bounceable, mainnet + testnet, url-safe + standard base64) and raw `workchain:hex` forms, with CRC16 validation.
- **Address re-serialization** — emit any parsed address in any target form via `Address::toString()` flags or `toTonscanFormat()`.
- **Pluggable RPC** — implement `WalletRpcInterface` (`getSeqno` + `sendBoc`) once and broadcast through toncenter, a custom node, or a test double.

## Why amashukov/ton-wallet-php

`olifanton/ton` covers parts of the TON wallet surface, but this package keeps the wallet contract, the transfer builder, and the address parser as small `final readonly` value objects with PHPStan level 9 across the board, and pins address derivation to a cross-checked `@ton/ton` parity vector. Broadcasting is never baked in — you bring the transport.

## Installation

```bash
composer require amashukov/ton-wallet-php
```

## Usage

### Derive the wallet address

```php
use Amashukov\TonCrypto\Mnemonic;
use Amashukov\TonWallet\WalletV4R2;

$keys   = Mnemonic::toKeyPair('word1 word2 ... word24');
$wallet = new WalletV4R2($keys);

echo $wallet->address()->toString();                   // user-friendly UQ… (non-bounceable)
echo $wallet->address()->toString(userFriendly: false); // raw 0:abc…  (for RPC calls)
```

### Build and broadcast a transfer

`InternalMessage` values are decimal-string nano-TON. `validUntil` is a UNIX
timestamp after which an unconfirmed external message is dropped by the network.

```php
use Amashukov\TonWallet\Address;
use Amashukov\TonWallet\InternalMessage;
use Amashukov\TonWallet\WalletV4R2;

$wallet = new WalletV4R2($keys);

$messages = [
    new InternalMessage(
        dest:  Address::parse('UQ...'),
        value: '1000000000',   // 1 TON in nano-TON
        bounce: false,
    ),
];

// One-shot: read seqno, sign, broadcast — through your WalletRpcInterface.
$wallet->sendTransfer($rpc, $messages, validUntil: time() + 60);
```

### Sign offline, broadcast later

```php
use Amashukov\TonCell\Boc;

$seqno = 0; // read out-of-band, or via $wallet->getSeqno($rpc)
$body  = $wallet->createTransfer($seqno, time() + 60, $messages);
$ext   = $wallet->wrapExternalInMessage($body);

$signedBocBase64 = Boc::encodeBase64($ext); // hand to any broadcaster
```

### Parse and re-serialize an address

```php
use Amashukov\TonWallet\Address;

$addr = Address::parse('EQDrjaLahLkMB-hMCmkzOyBuHJ139ZUYmPHu6RRBKnbdLIYI');

$addr->wc;                                  // 0
$addr->toString(userFriendly: false);       // 0:eb8d…  raw workchain:hex
$addr->toTonscanFormat();                    // bounceable url-safe EQ…
Address::isValid('not-an-address');          // false
```

## Requirements

- PHP 8.3+
- `ext-sodium` (Ed25519 signing)
- `ext-bcmath`

## Related packages

| Package | Role |
|---------|------|
| [amashukov/ton-cell-php](https://github.com/AndreyMashukov/ton-cell-php) | TLB Cell / Builder / BOC layer (dependency) |
| [amashukov/ton-crypto-php](https://github.com/AndreyMashukov/ton-crypto-php) | Mnemonic + Ed25519 KeyPair (dependency) |
| [amashukov/toncenter-client-php](https://github.com/AndreyMashukov/toncenter-client-php) | Typed toncenter v2 client + ready `WalletRpcInterface` adapter |
| [amashukov/ton-php](https://github.com/AndreyMashukov/ton-php) | TON meta-package |
| [amashukov/blockchain-context-bundle](https://github.com/AndreyMashukov/blockchain-context-bundle) | Symfony bundle wiring the whole family |

## Quality

- **PHPStan level 9**, clean.
- **php-cs-fixer** `@PER-CS` ruleset.
- **GitHub Actions CI** on every push.
- Address derivation pinned against a `@ton/ton` `WalletContractV4` parity vector.

## License

MIT — see [LICENSE](LICENSE).
