<?php

namespace App\Policies;

use App\Models\User;

class CotaPolicy
{
    public function viewAny(User $user): bool { return $user->can('cotas.ver'); }
    public function view(User $user): bool { return $user->can('cotas.ver'); }
    public function create(User $user): bool { return $user->can('cotas.criar'); }
    public function update(User $user): bool { return $user->can('cotas.editar'); }
    public function delete(User $user): bool { return $user->can('cotas.apagar'); }
}
