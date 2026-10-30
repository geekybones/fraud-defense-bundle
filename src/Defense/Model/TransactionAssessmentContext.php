<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final class TransactionAssessmentContext
{
    public function __construct(
        public readonly string $paymentMethod,
        public readonly string $cardBin = '',
        public readonly string $cardLastFour = '',
        public readonly string $currencyCode = '',
        public readonly float $value = 0.0,
        public readonly string $transactionId = '',
        public readonly ?TransactionUser $user = null,
        public readonly ?TransactionAddress $billingAddress = null,
        public readonly ?TransactionAddress $shippingAddress = null,
        public readonly float $shippingValue = 0.0,
    ) {
    }
}
