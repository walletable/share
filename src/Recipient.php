<?php

namespace Walletable\Share;

use Walletable\Models\Wallet;
use Walletable\Money\Money;

class Recipient
{
    /**
     * Wallet instance
     *
     * @var Wallet
     */
    protected $wallet;

    /**
     * Wallet instance
     *
     * @var Money
     */
    protected $amount;

    /**
     * Create recipient
     *
     * @param Wallet $wallets
     * @param \Walletable\Money\Money $amount
     */
    public function __construct(Wallet $wallet, Money $amount)
    {
        $this->wallet = $wallet;
        $this->amount = $amount;
    }

    /**
     * Get wallet
     *
     * @return Wallet
     */
    public function wallet(): Wallet
    {
        return $this->wallet;
    }

    /**
     * Get wallet
     *
     * @return Money
     */
    public function amount(): Money
    {
        return $this->amount;
    }
}
