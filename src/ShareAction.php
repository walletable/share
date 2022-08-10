<?php

namespace Walletable\Share;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Walletable\Internals\Actions\ActionData;
use Walletable\Internals\Actions\ActionInterface;
use Walletable\Models\Transaction;
use Walletable\Models\Wallet;
use Walletable\Share\Recipient;

class ShareAction implements ActionInterface
{
    protected $recipientModelsCache = [];

    protected $recipientResourcesCache = [];

    /**
     * Resourse of each model using a custom closure
     *
     * @var array
     */
    protected static $eachRecipientUsing = [];

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
    public function supportDebit(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportCredit(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function reversable(Transaction $transaction): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function reverse(Transaction $transaction, Transaction $new): ActionInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function methodResource(Transaction $transaction)
    {
        if (isset($this->recipientResourcesCache[$transaction->id])) {
            return $this->recipientResourcesCache[$transaction->id];
        }

        return $this->recipientResourcesCache[$transaction->id] = $this->recipientModelCollection($transaction)
        ->map(function ($model) {
            if (isset(static::$eachRecipientUsing[$model])) {
                return static::$eachRecipientUsing[$model];
            }

            return $model;
        });
    }

    /**
     * Build model colection from polymorphic IDs
     *
     * @param Transaction $transaction
     * @return Collection
     */
    protected function recipientModelCollection(Transaction $transaction)
    {
        if (isset($this->recipientModelsCache[$transaction->id])) {
            return $this->recipientModelsCache[$transaction->id];
        }

        $group = \collect($transaction->meta('recipients'))->mapToGroups(function ($item) {
            return [$item['type'] => $item['identifier']];
        });

        $models = \collect([]);

        $group->each(function (Collection $ids, $key) use ($models) {
            if (!($classExists = class_exists($key)) && !($morphClass = Relation::getMorphedModel($key))) {
                return;
            }

            $class = $classExists ? $key : $morphClass;

            $ids = $ids->toArray();

            $keyName = (new $class())->getKeyName();

            $class::query()->whereIn($keyName, $ids)->get()
                ->each(function ($model) use ($key, $models) {
                    $models[$model->getKey() . '_' . $key] = $model;
                });
        });

        return $this->recipientModelsCache[$transaction->id] = $models;
    }

    /**
     * Get the resourse of each model using a custom closure
     *
     * @param string $class
     * @param Closure $closure
     * @return void
     */
    public static function eachRecipientUsing(string $class, Closure $closure)
    {
        if (is_a($class, Model::class)) {
            static::$eachRecipientUsing[$class] = $closure;
        }
    }
}
