<?php

namespace App\Services\CustomerFlow\Actions;

use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\CustomerFlow\Support\ServiceNavBuilder;
use Illuminate\Support\Facades\Log;

class HomeAction implements ScreenActionInterface
{
    public function __construct(
        private ServiceNavBuilder $navBuilder,
    ) {}

    public function handledScreens(): array
    {
        return ['HOME_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        $navItems = $this->navBuilder->build();
        return [
            'screen' => 'HOME_SCREEN',
            'data' => [
                'enabled_services' => $navItems,
            ],
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        Log::debug('Flow: HomeAction dataExchange', ['data' => $data]);

        $trigger = $data['trigger'] ?? '';

        $triggerToScreen = [
            'nav_click' => null,
            'init_zesa' => 'ZESA_SCREEN',
            'init_zesa_calculator' => 'ZESA_CALCULATOR_SCREEN',
            'init_gift_cards' => 'GIFT_CARDS_SCREEN',
            'init_corporate_bills' => 'CORPORATE_BILLS_SCREEN',
            'init_airtime' => 'AIRTIME_SCREEN',
            'init_bundles' => 'BUNDLES_SCREEN',
            'init_telone' => 'TELONE_HOME_SCREEN',
            'init_billers' => 'BILLERS_SCREEN',
            'init_support' => 'SUPPORT_SCREEN',
        ];

        $targetScreen = $triggerToScreen[$trigger] ?? null;

        if (!$targetScreen) {
            $itemId = $data['id'] ?? '';
            $targetScreen = match ($itemId) {
                'ZESA_SCREEN' => 'ZESA_SCREEN',
                'AIRTIME_SCREEN' => 'AIRTIME_SCREEN',
                'BUNDLES_SCREEN' => 'BUNDLES_SCREEN',
                'TELONE_HOME_SCREEN' => 'TELONE_HOME_SCREEN',
                'BILLERS_SCREEN' => 'BILLERS_SCREEN',
                'SUPPORT_SCREEN' => 'SUPPORT_SCREEN',
                'GIFT_CARDS_SCREEN' => 'GIFT_CARDS_SCREEN',
                'CORPORATE_BILLS_SCREEN' => 'CORPORATE_BILLS_SCREEN',
                'ZESA_CALCULATOR_SCREEN' => 'ZESA_CALCULATOR_SCREEN',
                default => null,
            };
        }

        if ($targetScreen) {
            return [
                'screen' => $targetScreen,
                'data' => $data,
                '_init' => true,
            ];
        }

        $navItems = $this->navBuilder->build();
        return [
            'screen' => 'HOME_SCREEN',
            'data' => [
                'enabled_services' => $navItems,
            ],
        ];
    }
}
