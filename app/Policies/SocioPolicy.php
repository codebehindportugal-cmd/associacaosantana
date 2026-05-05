<?php

namespace App\Policies;

use App\Models\User;

class SocioPolicy
{
    public function viewAny(User $user): bool { return $user->can('socios.ver'); }
    public function view(User $user): bool { return $user->can('socios.ver'); }
    public function create(User $user): bool { return $user->can('socios.criar'); }
    public function update(User $user): bool { return $user->can('socios.editar'); }
    public function delete(User $user): bool { return $user->can('socios.apagar'); }
}
