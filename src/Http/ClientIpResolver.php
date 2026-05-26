<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Http;

use Symfony\Component\HttpFoundation\Request;

final class ClientIpResolver
{
    public function resolve(Request $request): string
    {
        $forwardedFor = $request->headers->get('X-Forwarded-For');
        if (null !== $forwardedFor && '' !== $forwardedFor) {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $candidate = trim($candidate);
                if ($this->isValidIp($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach (['X-Real-Ip', 'CF-Connecting-IP'] as $header) {
            $candidate = $request->headers->get($header);
            if (null !== $candidate && '' !== $candidate) {
                $candidate = trim($candidate);
                if ($this->isValidIp($candidate)) {
                    return $candidate;
                }
            }
        }

        $ip = $request->getClientIp();
        if ($this->isValidIp($ip)) {
            return (string) $ip;
        }

        return '';
    }

    private function isValidIp(?string $ip): bool
    {
        return null !== $ip
            && '' !== $ip
            && false !== filter_var($ip, \FILTER_VALIDATE_IP);
    }
}
