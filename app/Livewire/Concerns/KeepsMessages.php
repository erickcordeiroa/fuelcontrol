<?php

namespace App\Livewire\Concerns;

/**
 * Mensagens de sucesso e erro que ficam na tela até o usuário fechar. O flash da sessão some na próxima
 * requisição do Livewire (atualização automática, digitar num campo, marcar um pedido); aqui ele é copiado
 * para o estado do componente ao abrir a página.
 */
trait KeepsMessages
{
    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mountKeepsMessages(): void
    {
        $this->statusMessage = session('status');
        $this->errorMessage = session('error');
    }

    public function dismissMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    protected function notify(string $message): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
    }

    protected function notifyError(string $message): void
    {
        $this->errorMessage = $message;
        $this->statusMessage = null;
    }
}
