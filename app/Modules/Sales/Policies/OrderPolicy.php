<?php

namespace App\Modules\Sales\Policies;

use App\Modules\Sales\Models\Order;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\AuthorizesTenantMembership;

class OrderPolicy
{
    use AuthorizesTenantMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function view(User $user, Order $order): bool
    {
        return $this->isMember($user, $order->tenant);
    }

    public function create(User $user): bool
    {
        return $this->isMember($user, $this->tenant());
    }

    public function recordPayment(User $user, Order $order): bool
    {
        return $this->isMember($user, $order->tenant);
    }
}
