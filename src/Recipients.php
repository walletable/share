<?php

namespace Walletable\Share;

use Walletable\Models\Wallet;
use Walletable\Money\Money;

class Recipients
{
    /**
     * Recipient collection
     * @var array
     */
    protected $recipients;

    /**
     * Create new Recipients
     *
     * @param Recipient ...$recipients
     */
    public function __construct(Recipient ...$recipients)
    {
        $this->recipients = $recipients;
    }

    /**
     * Execute a callback over each recipient.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->recipients as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * Count recipients
     *
     * @return integer
     */
    public function count(): int
    {
        return count($this->recipients);
    }

    /**
     * Recipient array
     *
     * @return array
     */
    public function get(): array
    {
        return $this->recipients;
    }

    /**
     * Get the total amount
     *
     * @return Money
     */
    public function amount(): Money
    {
        return Money::sum(...\collect($this->recipients)->reduce(function ($result, $item) {
            $result[] = $item->amount();
            return $result;
        }, []));
    }

    /**
     * Allocate the amount to wallets
     *
     * @param Money $amount
     * @param Wallet ...$wallets
     * @return self
     */
    public static function allocate(Money $amount, Wallet ...$wallets): self
    {
        $shared = \collect($amount->allocateTo(count($wallets)));

        return new static(...$shared->reduce(function ($recipients, $item, $key) use ($wallets) {
            return $recipients[] = new Recipient($wallets[$key], $item);
        }, []));
    }
}
