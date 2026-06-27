<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Models\Customer;
use App\Services\CustomerFlow\CustomerFlowDispatcher;
use Illuminate\Support\Facades\Log;

trait CustomerFlowHandler
{
    abstract protected function customerFlowDispatcher(): CustomerFlowDispatcher;

    protected function handleCustomerInit(string $screen, array $data, Customer $customer): array
    {
        Log::debug('Flow: handleCustomerInit (dispatched)', ['screen' => $screen]);
        return $this->customerFlowDispatcher()->handleInit($screen, $data, $customer);
    }

    protected function handleCustomerDataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        Log::debug('Flow: handleCustomerDataExchange (dispatched)', ['screen' => $screen]);
        return $this->customerFlowDispatcher()->handleDataExchange($screen, $data, $customer, $flowToken);
    }

    protected function buildServiceNavItems(): array
    {
        return $this->customerFlowDispatcher()->buildServiceNavItems();
    }
}
