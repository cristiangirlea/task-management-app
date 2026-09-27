<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A free workspace is full. Rendered by the API handler as a 402 with the
 * message, which the frontend shows next to an upgrade link.
 */
class SeatLimitReachedException extends HttpException
{
    public function __construct(int $limit)
    {
        parent::__construct(402, __('billing.seat_limit', ['limit' => $limit]));
    }
}
