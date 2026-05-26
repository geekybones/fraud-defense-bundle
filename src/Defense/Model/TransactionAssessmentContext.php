<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Model;

final readonly class TransactionAssessmentContext
{
    public function __construct(
        public string $paymentMethod,
        public string $cardBin = '',
        public string $cardLastFour = '',
        public string $currencyCode = '',
        public float $value = 0.0,
        public string $transactionId = '',
        public ?TransactionUser $user = null,
        public ?TransactionAddress $billingAddress = null,
        public ?TransactionAddress $shippingAddress = null,
        public float $shippingValue = 0.0,
    ) {
    }
}
