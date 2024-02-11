<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle;

interface GuzzleEvents
{
    public const PRE_TRANSACTION = 'paradise_security_guzzle.pre_transaction';

    public const POST_TRANSACTION = 'paradise_security_guzzle.post_transaction';
}
