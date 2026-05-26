<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\Mapper\GoogleTransactionDataMapper;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAddress;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;
use PHPUnit\Framework\TestCase;

final class GoogleTransactionDataMapperTest extends TestCase
{
    public function test_maps_required_payment_fields(): void
    {
        $google = (new GoogleTransactionDataMapper())->map(new TransactionAssessmentContext(
            paymentMethod: 'credit-card',
            cardBin: '411111',
            cardLastFour: '1234',
        ));

        self::assertSame('credit-card', $google->getPaymentMethod());
        self::assertSame('411111', $google->getCardBin());
        self::assertSame('1234', $google->getCardLastFour());
    }

    public function test_maps_user_and_addresses(): void
    {
        $google = (new GoogleTransactionDataMapper())->map(new TransactionAssessmentContext(
            paymentMethod: 'credit-card',
            cardBin: '411111',
            cardLastFour: '1234',
            user: new TransactionUser(accountId: 'user-1', email: 'a@b.com'),
            billingAddress: new TransactionAddress(regionCode: 'US', postalCode: '94043'),
        ));

        self::assertSame('user-1', $google->getUser()?->getAccountId());
        self::assertSame('US', $google->getBillingAddress()?->getRegionCode());
    }
}
