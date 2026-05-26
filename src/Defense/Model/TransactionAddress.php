<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final readonly class TransactionAddress
{
    /**
     * @param list<string> $addressLines
     */
    public function __construct(
        public string $regionCode,
        public string $postalCode,
        public string $recipient = '',
        public array $addressLines = [],
        public string $locality = '',
        public string $administrativeArea = '',
    ) {
    }
}
