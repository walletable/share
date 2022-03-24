<?php

namespace Walletable\Share;

use RuntimeException;
use Walletable\Share\Share;

class IncompleteTransferException extends RuntimeException
{
    /**
     * Share instance
     *
     * @var \Walletable\Share\Share
     */
    protected $share;

    public function __construct(Share $share)
    {
        $this->share = $share;
    }

    /**
     * Get share object
     *
     * @return Share
     */
    public function getShare(): Share
    {
        return $this->share;
    }
}
