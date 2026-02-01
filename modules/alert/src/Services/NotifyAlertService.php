<?php

namespace Simp\Pindrop\Modules\alert\src\Services;

use Simp\Pindrop\Logger\LoggerInterface;

class NotifyAlertService
{
    public function __construct(protected LoggerInterface $logger)
    {
    }
}