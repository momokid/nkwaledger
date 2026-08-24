<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changed = $model->getChanges();

        // an update that changed nothing is not worth a row
        if ($changed === []) {
            return;
        }

        $before = array_intersect_key($model->getOriginal(), $changed);

        $this->record($model, 'updated', $before, $changed);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), null);
    }

    // one place decides what a row looks like, so every model reads the same way
    private function record(Model $model, string $action, ?array $before, ?array $after): void
    {
        $request = request();

        AuditLog::create([
            'user_id'        => auth()->id(),
            'action'         => $action,
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'old_values'     => $this->withoutSecrets($before),
            'new_values'     => $this->withoutSecrets($after),
            'ip_address'     => $request?->ip(),
            'user_agent'     => $request?->userAgent(),
        ]);
    }

    // a password hash or a token in the trail would turn the log into a target
    private function withoutSecrets(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_diff_key($values, array_flip([
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ]));
    }
}
