<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\Log;

trait UsesCustomerFlow
{
    public function shouldUseCustomerFlow(string $flowName): bool
    {
        if (!config('flows.customer.enabled', false)) {
            return false;
        }

        $enabledFlows = config('flows.customer.flows', []);

        if (empty($enabledFlows)) {
            return false;
        }

        return in_array($flowName, $enabledFlows, true);
    }

    public function isCustomerFlowEnabled(): bool
    {
        return config('flows.customer.enabled', false) === true;
    }

    public function getCustomerFlowId(): ?string
    {
        return config('whatsapp.flows.customer') ?? config('flows.customer.flow_id');
    }
}