<?php

namespace Walletable\Share;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Walletable\Facades\Wallet;
use Walletable\Share\Recipients;
use Walletable\Share\Share;
use Walletable\Share\ShareAction;

class ShareServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Config::get('walletable.models.wallet')::macro('share', function (Recipients $recipients, $remarks = null) {
            /**
             * @var \Walletable\Models\Wallet $this
             */
            return (new Share($this, $recipients, $remarks))->execute();
        });

        Wallet::action('share', ShareAction::class);
    }
}
