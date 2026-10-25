<?php

namespace App\Services\Teams;

use App\Models\User;

/**
 * The outcome of approving a team request.
 */
final readonly class Approval
{
    public function __construct(
        public ?User $user = null,
        public ?string $temporaryPassword = null,
        public ?string $delivery = null,
    ) {}
}
