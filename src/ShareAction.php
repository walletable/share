<?php

namespace Walletable\Transaction;

use Illuminate\Support\Str;
use Walletable\Internals\Actions\ActionData;
use Walletable\Internals\Actions\ActionInterface;
use Walletable\Models\Transaction;
use Walletable\Models\Wallet;
use Walletable\Share\Recipient;

class ShareAction implements ActionInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Transaction $transaction, ActionData $data)
    {
        /**
         * @var \Walletable\Models\Wallet
         */
        $sender = $data->argument(0)->isA(Wallet::class)->value();
        /**
         * @var \Walletable\Share\Recipients
         */
        $recipients = $data->argument(1)->isA(Recipients::class)->value();

        if ($transaction->type == 'credit') {
            $transaction->forceFill([
                'action' => 'share',
                'method_id' => $sender->walletable->getKey(),
                'method_type' => $sender->walletable->getMorphClass()
            ]);
        }

        if ($transaction->type == 'debit') {
            $transaction->forceFill([
                'action' => 'share'
            ])->meta('recipients', \collect($recipients->get())->reduce(function ($result, Recipient $item) {
                $result[] = [
                    'identifier' => $item->wallet()->walletable->getKey(),
                    'type' => $item->wallet()->walletable->getMorphClass(),
                    'amount' => $item->amount()->getInt()
                ];

                return $result;
            }));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function title(Transaction $transaction)
    {
        if ($transaction->type == 'credit') {
            return $transaction->method->getOwnerName();
        } else {
            $count = count($transaction->meta('recipients'));

            return sprintf(
                '%s to %d %s',
                $transaction->amount->display(),
                $count,
                Str::plural('person', $count)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function image(Transaction $transaction)
    {
        if ($transaction->type == 'credit') {
            return $transaction->method->getOwnerImage();
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function details(Transaction $transaction)
    {
        return \collect([]);
    }

    /**
     * {@inheritdoc}
     */
    public function suppportDebit(): bool
    {
        return false;
    }


    /**
     * {@inheritdoc}
     */
    public function suppportCredit(): bool
    {
        return false;
    }
}
