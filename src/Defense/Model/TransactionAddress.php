<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final class TransactionAddress
{
    /**
     * @param list<string> $addressLines
     */
    public function __construct(
        public readonly string $regionCode,
        public readonly string $postalCode,
        public readonly string $recipient = '',
        public readonly array $addressLines = [],
        public readonly string $locality = '',
        public readonly string $administrativeArea = '',
    ) {
    }
}
