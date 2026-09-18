<?php

namespace App\Observers;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(private AuditLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->model($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $old = array_intersect_key($model->getOriginal(), $changes);

        $this->logger->model($model, 'updated', $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->logger->model($model, 'deleted', $model->getOriginal(), []);
    }
}
