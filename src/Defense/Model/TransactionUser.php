<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final class TransactionUser
{
    public function __construct(
        public readonly string $accountId = '',
        public readonly string $email = '',
        public readonly string $phoneNumber = '',
    ) {
    }
}
