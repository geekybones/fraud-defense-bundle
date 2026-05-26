<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Defense\Mapper;

use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAddress;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionAssessmentContext;
use GeekyBones\FraudDefenseBundle\Defense\Model\TransactionUser;
use Google\Cloud\RecaptchaEnterprise\V1\TransactionData\Address;
use Google\Cloud\RecaptchaEnterprise\V1\TransactionData as GoogleTransactionData;
use Google\Cloud\RecaptchaEnterprise\V1\TransactionData\User;

final class GoogleTransactionDataMapper
{
    public function map(TransactionAssessmentContext $data): GoogleTransactionData
    {
        $google = new GoogleTransactionData();
        $google->setPaymentMethod($data->paymentMethod);

        if ('' !== $data->cardBin) {
            $google->setCardBin($data->cardBin);
        }

        if ('' !== $data->cardLastFour) {
            $google->setCardLastFour($data->cardLastFour);
        }

        if ('' !== $data->currencyCode) {
            $google->setCurrencyCode($data->currencyCode);
        }

        if ($data->value > 0) {
            $google->setValue($data->value);
        }

        if ('' !== $data->transactionId) {
            $google->setTransactionId($data->transactionId);
        }

        if ($data->shippingValue > 0) {
            $google->setShippingValue($data->shippingValue);
        }

        if ($data->user instanceof TransactionUser) {
            $google->setUser($this->mapUser($data->user));
        }

        if ($data->billingAddress instanceof TransactionAddress) {
            $google->setBillingAddress($this->mapAddress($data->billingAddress));
        }

        if ($data->shippingAddress instanceof TransactionAddress) {
            $google->setShippingAddress($this->mapAddress($data->shippingAddress));
        }

        return $google;
    }

    private function mapUser(TransactionUser $user): User
    {
        $google = new User();

        if ('' !== $user->accountId) {
            $google->setAccountId($user->accountId);
        }

        if ('' !== $user->email) {
            $google->setEmail($user->email);
        }

        if ('' !== $user->phoneNumber) {
            $google->setPhoneNumber($user->phoneNumber);
        }

        return $google;
    }

    private function mapAddress(TransactionAddress $address): Address
    {
        $google = new Address();
        $google->setRegionCode($address->regionCode);
        $google->setPostalCode($address->postalCode);

        if ('' !== $address->recipient) {
            $google->setRecipient($address->recipient);
        }

        if ([] !== $address->addressLines) {
            $google->setAddress($address->addressLines);
        }

        if ('' !== $address->locality) {
            $google->setLocality($address->locality);
        }

        if ('' !== $address->administrativeArea) {
            $google->setAdministrativeArea($address->administrativeArea);
        }

        return $google;
    }
}
