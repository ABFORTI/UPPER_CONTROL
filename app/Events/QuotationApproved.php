<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $cotizacionId,
        public ?int $actorClientId = null,
    ) {
    }
}
