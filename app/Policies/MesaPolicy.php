<?php

namespace App\Policies;

use App\Models\User;

class MesaPolicy
{
    public function viewAny(User $user): bool { return $user->can('mesas.ver'); }
    public function view(User $user): bool { return $user->can('mesas.ver'); }
    public function create(User $user): bool { return $user->can('mesas.criar'); }
    public function update(User $user): bool { return $user->can('mesas.editar'); }
    public function delete(User $user): bool { return $user->can('mesas.apagar'); }
}
