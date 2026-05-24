<?php

declare(strict_types=1);

namespace Amashukov\TonWallet;

use Amashukov\TonCell\AddressData;
use InvalidArgumentException;

final readonly class Address
{
    public const int TAG_BOUNCABLE     = 0x11;

    public const int TAG_NON_BOUNCABLE = 0x51;

    public const int FLAG_TESTNET      = 0x80;

    public function __construct(
        public int $wc,
        public string $hashPart,
        public bool $isTestOnly = false,
        public bool $isBounceable = true,
        public bool $isUserFriendly = true,
        public bool $isUrlSafe = true,
    ) {
        if (0 !== $this->wc && -1 !== $this->wc) {
            throw new InvalidArgumentException(sprintf('Workchain must be 0 or -1, got %d', $this->wc));
        }
        if (32 !== \strlen($this->hashPart)) {
            throw new InvalidArgumentException('Address hash part must be a 32-byte binary string');
        }
    }

    public static function parse(self|string $input): self
    {
        if ($input instanceof self) {
            return clone $input;
        }

        if (1 === preg_match('/^(?<wc>-?\d):(?<hashpart>[a-f\d]{64})$/i', $input, $match)) {
            $hashPart = hex2bin($match['hashpart']);
            if (false === $hashPart) {
                throw new InvalidArgumentException('Address: raw hashpart is not valid hex');
            }

            return new self(
                wc: (int) $match['wc'],
                hashPart: $hashPart,
                isUserFriendly: false,
            );
        }

        if (48 === \strlen($input)) {
            return self::parseHumanReadable($input);
        }

        throw new InvalidArgumentException('Invalid address format');
    }

    public static function isValid(self|string $input): bool
    {
        try {
            self::parse($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function toCellData(): AddressData
    {
        return new AddressData($this->wc, $this->hashPart);
    }

    public function toString(
        ?bool $userFriendly = null,
        ?bool $urlSafe = null,
        ?bool $bounceable = null,
        ?bool $testOnly = null,
    ): string {
        $userFriendly ??= $this->isUserFriendly;
        $urlSafe      ??= $this->isUrlSafe;
        $bounceable   ??= $this->isBounceable;
        $testOnly     ??= $this->isTestOnly;

        if (!$userFriendly) {
            return sprintf('%d:%s', $this->wc, bin2hex($this->hashPart));
        }

        $tag = $bounceable ? self::TAG_BOUNCABLE : self::TAG_NON_BOUNCABLE;
        if ($testOnly) {
            $tag |= self::FLAG_TESTNET;
        }

        $address         = pack('cca*', $tag, $this->wc, $this->hashPart);
        $addressWithHash = $address . Utils::crc16($address);

        return Utils::base64encode($addressWithHash, $urlSafe);
    }

    public function toTonscanFormat(): string
    {
        return $this->toString(userFriendly: true, urlSafe: true, bounceable: true);
    }

    private static function parseHumanReadable(string $input): self
    {
        $addressBytes = Utils::base64decode($input);
        if (36 !== \strlen($addressBytes)) {
            throw new InvalidArgumentException('Invalid address format: length must be 36 bytes');
        }

        $address = unpack('ctag/cwc/a32hashpart/a2crc16', $addressBytes);
        if (false === $address) {
            throw new InvalidArgumentException('Invalid address format: could not unpack');
        }

        $crc16hash = Utils::crc16(substr($addressBytes, 0, 34));
        if ($crc16hash !== $address['crc16']) {
            throw new InvalidArgumentException('Wrong crc16 hashsum');
        }

        $tag        = (int) $address['tag'];
        $isUrlSafe  = !str_contains($input, '+') && !str_contains($input, '/');
        $isTestOnly = false;

        if (0 !== ($tag & self::FLAG_TESTNET)) {
            $isTestOnly = true;
            $tag        = Utils::signedHexAbs($tag ^ self::FLAG_TESTNET);
        }

        if (self::TAG_BOUNCABLE !== $tag && self::TAG_NON_BOUNCABLE !== $tag) {
            throw new InvalidArgumentException(sprintf('Invalid tag: expected %d or %d, given %d', self::TAG_BOUNCABLE, self::TAG_NON_BOUNCABLE, $tag));
        }

        return new self(
            wc: (int) $address['wc'],
            hashPart: (string) $address['hashpart'],
            isTestOnly: $isTestOnly,
            isBounceable: self::TAG_BOUNCABLE === $tag,
            isUserFriendly: true,
            isUrlSafe: $isUrlSafe,
        );
    }
}
