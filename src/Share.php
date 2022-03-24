<?php

namespace Walletable\Share;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Walletable\Exceptions\IncompactibleWalletsException;
use Walletable\Exceptions\InsufficientBalanceException;
use Walletable\Facades\Wallet as Manager;
use Walletable\Internals\Actions\ActionData;
use Walletable\Internals\Lockers\LockerInterface;
use Walletable\Transaction\TransactionBag;
use Walletable\Models\Wallet;
use Walletable\Money\Money;
use Walletable\Share\IncompleteTransferException;

class Share
{
    /**
     * Sender wallet
     *
     * @var \Walletable\Models\Wallet
     */
    protected $sender;

    /**
     * Recipient wallets
     *
     * @var \Walletable\Share\Recipients
     */
    protected $recipients;

    /**
     * Trasanction bads
     *
     * @var \Walletable\Transaction\TransactionBag
     */
    protected $bag;

    /**
     * Share status
     *
     * @var bool
     */
    protected $successful = false;

    /**
     * Success count
     *
     * @var bool
     */
    protected $successfulCount = 0;

    /**
     * Note added to the transfer
     *
     * @var string|null
     */
    protected $remarks;

    /**
     * The session id of the transfer
     *
     * @var bool
     */
    protected $session;

    /**
     * The transfer locker
     *
     * @var \Walletable\Internals\Lockers\OptimisticLocker
     */
    protected $locker;

    public function __construct(Wallet $sender, Recipients $recipients, string $remarks = null)
    {
        $this->sender = $sender;
        $this->recipients = $recipients;
        $this->amount = $recipients->amount();
        $this->remarks = $remarks;
        $this->session = Str::uuid();
        $this->bag = new TransactionBag();
    }

    /**
     * Execute the transfer
     *
     * @return self
     */
    public function execute(): self
    {
        $this->checks();

        try {
            DB::beginTransaction();

            if ($this->debitSender()) {
                $this->recipients->each(function (Recipient $recipient) {
                    $transaction = $this->bag->new($this->receiver, [
                        'type' => 'credit',
                        'session' => $this->session,
                        'remarks' => $this->remarks
                    ]);

                    if ($this->locker()->creditLock($recipient->wallet(), $recipient->amount(), $transaction)) {
                        $this->successfulCount++;
                    }
                });

                if ($this->successfulCount !== $this->recipients->count()) {
                    throw new IncompleteTransferException($this);
                }

                $this->successful = true;

                Manager::applyAction('share', $this->bag, new ActionData(
                    $this->sender,
                    $this->recipients
                ));
                $this->bag->each(function ($item) {
                    $item->forceFill([
                        'created_at' => now()
                    ])->save();
                });
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $this;
    }

    /**
     * Debit the sender
     */
    protected function debitSender()
    {
        $transaction = $this->bag->new($this->sender, [
            'type' => 'debit',
            'session' => $this->session,
            'remarks' => $this->remarks
        ]);

        if ($this->locker()->debitLock($this->sender, $this->amount, $transaction)) {
            return true;
        }
    }

    /**
     * Run some compulsory checks
     *
     * @return void
     */
    protected function checks()
    {
        if ($this->sender->amount->lessThan($this->amount)) {
            throw new InsufficientBalanceException($this->sender, $this->amount);
        }

        $this->recipients->each(function ($item) {
            if (!$this->sender->compactible($item->wallet())) {
                throw new IncompactibleWalletsException($this->sender, $item->wallet());
            }
        });
    }

    /**
     * Get transaction bag
     *
     * @return \Walletable\Transaction\TransactionBag
     */
    public function getTransactions(): TransactionBag
    {
        return $this->bag;
    }

    /**
     * Get amount
     *
     * @return \Walletable\Money\Money
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * Get the locker for the transfer
     */
    protected function locker(): LockerInterface
    {
        if ($this->locker) {
            return $this->locker;
        }
        return $this->locker = Manager::locker(config('walletable.locker'));
    }
}
