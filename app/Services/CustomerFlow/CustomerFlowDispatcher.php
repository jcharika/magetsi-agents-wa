<?php

namespace App\Services\CustomerFlow;

use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\CustomerFlow\Support\ServiceNavBuilder;
use Illuminate\Support\Facades\Log;

class CustomerFlowDispatcher
{
    private array $actionMap = [];

    public function __construct(
        private ServiceNavBuilder $navBuilder,
    ) {}

    public function registerAction(ScreenActionInterface $action): void
    {
        foreach ($action->handledScreens() as $screen) {
            $this->actionMap[$screen] = $action;
        }
    }

    public function handleInit(string $screen, array $data, Customer $customer): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        Log::debug('Flow: handleCustomerInit', ['screen' => $screen, 'hasData' => !empty($data)]);

        if (!$screen || $screen === 'HOME_SCREEN') {
            $navItems = $this->navBuilder->build();
            return [
                'screen' => 'HOME_SCREEN',
                'data' => [
                    'enabled_services' => $navItems,
                ],
            ];
        }

        $disabled = $this->navBuilder->serviceDisabledResponse($screen);
        if ($disabled) return $disabled;

        $action = $this->actionMap[$screen] ?? null;
        if ($action) {
            return $action->init($screen, $data, $customer);
        }

        Log::warning('Flow: Unknown screen in init', ['screen' => $screen]);
        return [
            'screen' => 'HOME_SCREEN',
            'data' => [
                'enabled_services' => $this->navBuilder->build(),
            ],
        ];
    }

    public function handleDataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        Log::debug('Flow: handleCustomerDataExchange', ['screen' => $screen, 'data' => $data]);

        $action = $this->actionMap[$screen] ?? null;
        if ($action) {
            $result = $action->dataExchange($screen, $data, $customer, $flowToken);

            if (isset($result['_init'])) {
                $initScreen = $result['screen'];
                unset($result['_init']);
                return $this->handleInit($initScreen, $result['data'] ?? [], $customer);
            }

            return $result;
        }

        return [
            'screen' => 'HOME_SCREEN',
            'data' => [
                'enabled_services' => $this->navBuilder->build(),
            ],
        ];
    }

    public function buildServiceNavItems(): array
    {
        return $this->navBuilder->build();
    }

    public function getActionMap(): array
    {
        return $this->actionMap;
    }
}
