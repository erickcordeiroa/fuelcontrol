<?php

namespace App\Livewire\Concerns;

trait ConfirmsDeletes
{
    public bool $showDeleteModal = false;

    public ?int $pendingDeleteId = null;

    public function openDeleteModal(int $id): void
    {
        $this->pendingDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->pendingDeleteId = null;
    }

    public function confirmPendingDelete(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $id = $this->pendingDeleteId;
        $this->closeDeleteModal();
        $this->delete($id);
    }

    abstract public function delete(int $id): void;
}
