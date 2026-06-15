<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    private array $ignoredFields = [
        'created_at',
        'updated_at',
        'email_verified_at',
        'remember_token',
    ];

    private array $sensitiveFields = [
        'password',
        'pin',
        'cliente_token',
        'token',
        'remember_token',
    ];

    public function created(Model $model): void
    {
        $values = $this->cleanValues($model->getAttributes());

        if ($values !== []) {
            $this->write('criado', $model, null, $values);
        }
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), $this->ignoredFields);

        if ($changes === []) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($changes) as $field) {
            $oldValues[$field] = $model->getOriginal($field);
        }

        $this->write('alterado', $model, $this->cleanValues($oldValues), $this->cleanValues($changes));
    }

    public function deleted(Model $model): void
    {
        $this->write('apagado', $model, $this->cleanValues($model->getOriginal()), null);
    }

    private function write(string $action, Model $model, ?array $oldValues, ?array $newValues): void
    {
        $request = request();
        $user = Auth::user();
        $hasSession = $request->hasSession();

        AuditLog::create([
            'user_id' => $user?->id,
            'pos_id' => $hasSession ? $request->session()->get('pos_id') : null,
            'actor_name' => $user?->name ?: ($hasSession ? $request->session()->get('pos_operador') : null),
            'actor_type' => $user ? 'backoffice' : ($hasSession && $request->session()->has('pos_id') ? 'pos' : 'sistema'),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'auditable_label' => $this->label($model),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    private function cleanValues(array $values): array
    {
        $values = Arr::except($values, $this->ignoredFields);

        foreach ($this->sensitiveFields as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[oculto]';
            }
        }

        return collect($values)
            ->map(fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value)
            ->all();
    }

    private function label(Model $model): ?string
    {
        foreach (['nome', 'name', 'titulo', 'title', 'numero_socio', 'ponto', 'email'] as $field) {
            if (! blank($model->getAttribute($field))) {
                return (string) $model->getAttribute($field);
            }
        }

        return null;
    }
}
