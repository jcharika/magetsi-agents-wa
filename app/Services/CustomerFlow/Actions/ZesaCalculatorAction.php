<?php

namespace App\Services\CustomerFlow\Actions;

use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\MeterValidationService;
use App\Services\ZesaCalculatorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ZesaCalculatorAction implements ScreenActionInterface
{
    public function __construct(
        private MeterValidationService $meterService,
        private ZesaCalculatorService $calculator,
    ) {}

    public function handledScreens(): array
    {
        return ['ZESA_CALCULATOR_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        Log::debug('Flow: ZESA_CALCULATOR_SCREEN init data');
        return [
            'screen' => 'ZESA_CALCULATOR_SCREEN',
            'data' => [
                'meter_valid' => false,
                'customer_name' => '',
                'customer_address' => '',
                'calculation_modes' => [
                    ['id' => 'units', 'title' => 'Units (kWh)'],
                    ['id' => 'amount', 'title' => 'Amount (ZWG)'],
                ],
                'calc_total_cost' => '',
                'calc_energy_charge' => '',
                'calc_re_levy' => '',
                'calc_units' => '',
                'calc_tariff_band' => '',
            ],
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_meter_number') {
            return $this->verifyMeter($data['meter_number'] ?? '');
        }

        if ($trigger === 'calculate_zesa') {
            return $this->calculate($data);
        }

        return [
            'screen' => 'ZESA_CALCULATOR_SCREEN',
            'data' => ['error_message' => 'Invalid action.'],
        ];
    }

    private function verifyMeter(string $meterNumber): array
    {
        $result = Cache::remember("validation/$meterNumber", 360, function () use ($meterNumber) {
            return $this->meterService->validate($meterNumber);
        });

        return [
            'screen' => 'ZESA_CALCULATOR_SCREEN',
            'data' => [
                'meter_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid meter number.',
            ],
        ];
    }

    private function calculate(array $data): array
    {
        $meterNumber = $data['meter_number'] ?? '';
        $mode = $data['mode'] ?? 'units';
        $units = (float)($data['units'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);

        if (!$meterNumber) {
            return [
                'screen' => 'ZESA_CALCULATOR_SCREEN',
                'data' => ['error_message' => 'Please enter a meter number.'],
            ];
        }

        if ($mode === 'units' && $units <= 0) {
            return [
                'screen' => 'ZESA_CALCULATOR_SCREEN',
                'data' => ['error_message' => 'Please enter a valid number of units.'],
            ];
        }

        if ($mode === 'amount' && $amount <= 0) {
            return [
                'screen' => 'ZESA_CALCULATOR_SCREEN',
                'data' => ['error_message' => 'Please enter a valid amount.'],
            ];
        }

        try {
            if ($mode === 'units') {
                $result = $this->calculator->estimateByUnits($meterNumber, $units);

                if (!$result['success']) {
                    return [
                        'screen' => 'ZESA_CALCULATOR_SCREEN',
                        'data' => ['error_message' => 'Failed to calculate. Please try again.'],
                    ];
                }

                return [
                    'screen' => 'ZESA_CALCULATOR_SCREEN',
                    'data' => [
                        'calc_total_cost' => $result['total_cost'],
                        'calc_energy_charge' => $result['energy_charge'],
                        'calc_re_levy' => $result['re_levy'],
                        'calc_units' => $result['expected_units'],
                        'calc_tariff_band' => $result['tariff_band'],
                    ],
                ];
            }

            if ($mode === 'amount') {
                $result = $this->calculator->estimateByAmount($meterNumber, $amount);

                return [
                    'screen' => 'ZESA_CALCULATOR_SCREEN',
                    'data' => [
                        'calc_total_cost' => $result['total_zwg'],
                        'calc_energy_charge' => $result['energy_charge'],
                        'calc_re_levy' => $result['re_levy'],
                        'calc_units' => $result['units'],
                        'calc_tariff_band' => $result['tariff_band'] ?? '',
                    ],
                ];
            }

            return [
                'screen' => 'ZESA_CALCULATOR_SCREEN',
                'data' => ['error_message' => 'Invalid calculation mode.'],
            ];
        } catch (\Exception $e) {
            Log::error('ZESA Calculator error: ' . $e->getMessage());
            return [
                'screen' => 'ZESA_CALCULATOR_SCREEN',
                'data' => ['error_message' => 'Calculation failed. Please try again later.'],
            ];
        }
    }
}
