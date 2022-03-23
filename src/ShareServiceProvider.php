<?php

namespace Walletable;

use Illuminate\Support\ServiceProvider;
use Walletable\Facades\Wallet;
use Walletable\Transaction\TransferAction;

class WalletableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Wallet::action('share', TransferAction::class);
    }
}
