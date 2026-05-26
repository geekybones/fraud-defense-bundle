<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final readonly class TransactionUser
{
    public function __construct(
        public string $accountId = '',
        public string $email = '',
        public string $phoneNumber = '',
    ) {
    }
}
