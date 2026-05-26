<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\Tests\Http;

use GeekyBones\FraudDefenseBundle\Http\ClientIpResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ClientIpResolverTest extends TestCase
{
    private ClientIpResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ClientIpResolver();
    }

    public function test_uses_client_ip_when_available(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        self::assertSame('203.0.113.10', $this->resolver->resolve($request));
    }

    public function test_falls_back_to_first_valid_ip_in_x_forwarded_for(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '198.51.100.5, 10.0.0.1');

        self::assertSame('198.51.100.5', $this->resolver->resolve($request));
    }

    public function test_falls_back_to_x_real_ip(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Real-Ip', '198.51.100.8');

        self::assertSame('198.51.100.8', $this->resolver->resolve($request));
    }

    public function test_falls_back_to_cf_connecting_ip(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('CF-Connecting-IP', '198.51.100.9');

        self::assertSame('198.51.100.9', $this->resolver->resolve($request));
    }

    public function test_returns_empty_string_when_no_valid_ip_found(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Forwarded-For', 'not-an-ip');
        $request->server->remove('REMOTE_ADDR');

        self::assertSame('', $this->resolver->resolve($request));
    }

    public function test_prefers_forwarded_for_over_direct_connection_ip(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '203.0.113.20');

        self::assertSame('203.0.113.20', $this->resolver->resolve($request));
    }
}
