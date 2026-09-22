<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Collection;
use Stripe\StripeClient;
use Stripe\Subscription;

/**
 * Test double for Stripe\StripeClient.
 *
 * Records checkout creation params and returns fake Stripe API objects
 * via StripeObject::constructFrom() so that ->url, ->id etc. behave
 * like real responses.
 *
 * Usage in unit tests:
 *   $fake = new FakeStripeClient();
 *   $fake->checkoutSessionUrl = 'https://checkout.stripe.com/c/pay/cs_test';
 *   $sut = new StripeService($fake, ...);
 *
 * Usage in integration tests:
 *   Override 'stripe.client' in services_test.yaml to point to this class.
 */
final class FakeStripeClient extends StripeClient
{
    /** URL returned by createCheckoutSession (set before call, read after). */
    public string $checkoutSessionUrl = 'https://checkout.stripe.com/c/pay/cs_test_abc';

    /**
     * @var array{
     *     line_items?: list<array{price?: string, quantity?: int}>,
     *     mode?: string,
     *     customer?: string,
     *     customer_email?: string,
     *     client_reference_id?: string,
     *     metadata?: array<string, string>,
     *     automatic_tax?: array{enabled?: bool},
     *     success_url?: string,
     *     cancel_url?: string
     * }
     */
    public array $checkoutCreateParams = [];

    /** Set this to make checkout->sessions->create() throw. */
    public ?\Throwable $checkoutException = null;

    /**
     * @var list<array{
     *     id?: string,
     *     status?: string,
     *     items?: array{data?: list<array{price?: string|array{id?: string}}>}
     * }>
     */
    public array $subscriptionListData = [];

    /** Set this to make subscriptions->all() throw. */
    public ?\Throwable $subscriptionsException = null;

    /** Portal URL returned by billingPortal->sessions->create(). */
    public string $portalUrl = 'https://billing.stripe.com/p/session/test';

    /** Set this to make billingPortal->sessions->create() throw. */
    public ?\Throwable $portalException = null;

    public function __construct()
    {
        parent::__construct(['api_key' => 'sk_test_dummy']);
    }

    #[\Override]
    public function __get(mixed $name): mixed
    {
        if (!\is_string($name)) {
            throw new \InvalidArgumentException('Stripe service names must be strings.');
        }

        return match ($name) {
            'checkout' => new readonly class($this) {
                public function __construct(private FakeStripeClient $fake) {}

                public function __get(mixed $name): mixed
                {
                    if (!\is_string($name)) {
                        throw new \InvalidArgumentException('Checkout service names must be strings.');
                    }

                    if ($name === 'sessions') {
                        return new readonly class($this->fake) {
                            public function __construct(private FakeStripeClient $fake) {}

                            /** @phpstan-param array{line_items?: list<array{price?: string, quantity?: int}>, mode?: string, customer?: string, customer_email?: string, client_reference_id?: string, metadata?: array<string, string>, automatic_tax?: array{enabled?: bool}, success_url?: string, cancel_url?: string} $params */
                            public function create(array $params): CheckoutSession
                            {
                                $this->fake->checkoutCreateParams = $params;

                                if ($this->fake->checkoutException) {
                                    throw $this->fake->checkoutException;
                                }

                                /** @var CheckoutSession $session */
                                $session = CheckoutSession::constructFrom([
                                    'id' => 'cs_test_123',
                                    'object' => 'checkout.session',
                                    'mode' => $params['mode'] ?? 'subscription',
                                    'url' => $this->fake->checkoutSessionUrl,
                                    'metadata' => $params['metadata'] ?? [],
                                ]);

                                return $session;
                            }
                        };
                    }

                    throw new \RuntimeException("FakeStripeClient: unknown checkout service '{$name}'");
                }
            },
            'subscriptions' => new readonly class($this) {
                public function __construct(private FakeStripeClient $fake) {}

                /**
                 * @phpstan-param array{customer?: string, status?: string, limit?: int} $params
                 * @phpstan-return Collection<Subscription>
                 */
                public function all(array $params = []): Collection
                {
                    if ($this->fake->subscriptionsException) {
                        throw $this->fake->subscriptionsException;
                    }

                    /** @var Collection<Subscription> */
                    return Collection::constructFrom([
                        'object' => 'list',
                        'data' => $this->fake->subscriptionListData,
                        'has_more' => false,
                    ]);
                }
            },
            'billingPortal' => new readonly class($this) {
                public function __construct(private FakeStripeClient $fake) {}

                public function __get(mixed $name): mixed
                {
                    if (!\is_string($name)) {
                        throw new \InvalidArgumentException('Billing service names must be strings.');
                    }

                    if ($name === 'sessions') {
                        return new readonly class($this->fake) {
                            public function __construct(private FakeStripeClient $fake) {}

                            /** @phpstan-param array{customer: string, return_url: string} $params */
                            public function create(array $params): PortalSession
                            {
                                if ($this->fake->portalException) {
                                    throw $this->fake->portalException;
                                }

                                /** @var PortalSession $session */
                                $session = PortalSession::constructFrom([
                                    'id' => 'bps_test_123',
                                    'object' => 'billing_portal.session',
                                    'url' => $this->fake->portalUrl,
                                ]);

                                return $session;
                            }
                        };
                    }

                    throw new \RuntimeException("FakeStripeClient: unknown billingPortal service '{$name}'");
                }
            },
            default => throw new \RuntimeException("FakeStripeClient: unknown service '{$name}'"),
        };
    }
}
