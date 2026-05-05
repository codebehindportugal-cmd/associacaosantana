<?php

namespace App\Policies;

use App\Models\User;

class PedidoPolicy
{
    public function viewAny(User $user): bool { return $user->can('pedidos.ver'); }
    public function view(User $user): bool { return $user->can('pedidos.ver'); }
    public function create(User $user): bool { return $user->can('pedidos.criar'); }
    public function update(User $user): bool { return $user->can('pedidos.editar'); }
    public function delete(User $user): bool { return $user->can('pedidos.apagar'); }
}
