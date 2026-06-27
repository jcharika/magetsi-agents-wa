<?php

namespace App\Services\CustomerFlow\Actions;

use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use Illuminate\Support\Facades\Log;

class SupportAction implements ScreenActionInterface
{
    public function handledScreens(): array
    {
        return ['SUPPORT_SCREEN', 'GIFT_CARDS_SCREEN', 'CORPORATE_BILLS_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        Log::debug("Flow: $screen init data");
        return [
            'screen' => $screen,
            'data' => [],
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        return [
            'screen' => $screen,
            'data' => [],
        ];
    }
}
