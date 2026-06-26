<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ZesaCalculatorService
{
    private const API_URL = 'https://www.calculators.co.zw/api/get-meter-data/';
    private const API_TOKEN = 'c4feb4aa15f95043255cd14a1d12fb727df920a7';

    private const DEFAULT_TARIFF_BANDS = [
        ['range' => '0-50', 'price' => 352.63, 'limit' => 50, 'is_final' => false],
        ['range' => '51-100', 'price' => 422.04, 'limit' => 50, 'is_final' => false],
        ['range' => '101-200', 'price' => 739.72, 'limit' => 100, 'is_final' => false],
        ['range' => '201-300', 'price' => 1050.52, 'limit' => 100, 'is_final' => false],
        ['range' => '301-400', 'price' => 1192.35, 'limit' => 100, 'is_final' => false],
    ];

    private const DEFAULT_FINAL_BAND_PRICE = 1356.42;
    private const RE_LEVY_RATIO = 0.06 / 0.94;
    private const CACHE_MINUTES = 5;
    private const SCALE = 6;
    private const HTTP_TIMEOUT = 10;

    public function meterInfo(string $meterNumber): array
    {
        $cacheKey = "zesa-calculator-meter-info-{$meterNumber}";

        return Cache::remember($cacheKey, self::CACHE_MINUTES * 60, function () use ($meterNumber) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Token ' . self::API_TOKEN,
                ])
                    ->timeout(self::HTTP_TIMEOUT)
                    ->get(self::API_URL, [
                        'meter_number' => $meterNumber,
                        'units' => 1,
                    ])
                    ->throw();

                $data = $response->json();

                return [
                    'success' => true,
                    'month_to_date_units' => $data['month_to_date_units'] ?? 0,
                    'current_tariff_band' => $data['current_tariff_band'] ?? '',
                ];
            } catch (\Exception $e) {
                Log::warning('ZESA calculator meter info failed', [
                    'meter' => $meterNumber,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'month_to_date_units' => 0,
                    'current_tariff_band' => '',
                ];
            }
        });
    }

    public function estimateByUnits(string $meterNumber, float $units): array
    {
        $cacheKey = "zesa-calculator-units-{$meterNumber}-{$units}";

        return Cache::remember($cacheKey, self::CACHE_MINUTES * 60, function () use ($meterNumber, $units) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Token ' . self::API_TOKEN,
                ])
                    ->timeout(self::HTTP_TIMEOUT)
                    ->get(self::API_URL, [
                        'meter_number' => $meterNumber,
                        'units' => $units,
                    ])
                    ->throw();

                $data = $response->json();

                Log::debug('ZESA calculator by units', [
                    'meter' => $meterNumber,
                    'units' => $units,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'total_cost' => $data['total_cost'] ?? '',
                    'energy_charge' => $data['energy_charge'] ?? '',
                    're_levy' => $data['re_levy'] ?? '',
                    'expected_units' => $data['expected_energy'] ?? '',
                    'month_to_date_units' => $data['month_to_date_units'] ?? '',
                    'tariff_band' => $data['current_tariff_band'] ?? '',
                ];
            } catch (\Exception $e) {
                Log::error('ZESA calculator by units failed', [
                    'meter' => $meterNumber,
                    'units' => $units,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'total_cost' => '',
                    'energy_charge' => '',
                    're_levy' => '',
                    'expected_units' => '',
                    'month_to_date_units' => '',
                    'tariff_band' => '',
                ];
            }
        });
    }

    public function estimateByAmount(string $meterNumber, float $amount): array
    {
        $meterInfo = $this->meterInfo($meterNumber);
        $previousUnits = $meterInfo['success'] ? (float)$meterInfo['month_to_date_units'] : 0;

        $result = $this->calculateUnitsForAmount($previousUnits, $amount);

        $result['tariff_band'] = $meterInfo['current_tariff_band'] ?? '';

        return $result;
    }

    public function calculateUnitsCost(float $previousUnits, float $unitsToPurchase): array
    {
        $bands = $this->getTariffBands();
        $finalBandPrice = $this->getFinalBandPrice();
        $output = [];
        $unitsCost = 0;
        $remainingUnits = $unitsToPurchase;
        $adjustedBands = $this->adjustBandsForPreviousUsage($bands, $previousUnits);

        foreach ($adjustedBands as $key => $band) {
            if ($remainingUnits <= 0) {
                break;
            }

            $usage = min($remainingUnits, $band['remaining']);
            $bandAmount = $usage * $band['price'];
            $unitsCost += $bandAmount;
            $remainingUnits -= $usage;

            $output[] = [
                'band' => $key + 1,
                'limit' => $band['limit'],
                'price' => $band['price'],
                'used' => round($bandAmount, 2),
                'units' => round($usage, 2),
            ];
        }

        if ($remainingUnits > 0) {
            $unitsCost += $remainingUnits * $finalBandPrice;
            $output[] = [
                'band' => count($adjustedBands) + 1,
                'limit' => 'Unlimited',
                'price' => $finalBandPrice,
                'used' => round($remainingUnits * $finalBandPrice, 2),
                'units' => round($remainingUnits, 2),
            ];
        }

        $reLevy = $unitsCost * self::RE_LEVY_RATIO;
        $totalCost = $unitsCost + $reLevy;

        return [
            'total_zwg' => round($totalCost, 2),
            'units' => round($unitsToPurchase, 2),
            'energy_charge' => round($unitsCost, 2),
            're_levy' => round($reLevy, 2),
            'band_breakdown' => $output,
        ];
    }

    public function calculateUnitsForAmount(float $previousUnits, float $amount): array
    {
        $reLevy = $amount * 0.06;
        $energyCharge = $amount - $reLevy;
        $unitsBought = 0;
        $remainingAmount = $energyCharge;
        $output = [];

        $bands = $this->getTariffBands();
        $finalBandPrice = $this->getFinalBandPrice();
        $adjustedBands = $this->adjustBandsForPreviousUsage($bands, $previousUnits);

        foreach ($adjustedBands as $key => $band) {
            $cost = $band['remaining'] * $band['price'];

            if ($remainingAmount >= $cost) {
                $unitsBought += $band['remaining'];
                $remainingAmount -= $cost;

                $output[] = [
                    'band' => $key + 1,
                    'limit' => $band['limit'],
                    'price' => $band['price'],
                    'used' => round($cost, 2),
                    'units' => round($band['remaining'], 2),
                ];
            } else {
                $partialUnits = $remainingAmount / $band['price'];
                $unitsBought += $partialUnits;

                $output[] = [
                    'band' => $key + 1,
                    'limit' => $band['limit'],
                    'price' => $band['price'],
                    'used' => round($remainingAmount, 2),
                    'units' => round($partialUnits, 2),
                ];

                $remainingAmount = 0;
                break;
            }
        }

        if ($remainingAmount > 0) {
            $extraUnits = $remainingAmount / $finalBandPrice;
            $unitsBought += $extraUnits;

            $output[] = [
                'band' => count($adjustedBands) + 1,
                'limit' => 'Unlimited',
                'price' => $finalBandPrice,
                'used' => round($remainingAmount, 2),
                'units' => round($extraUnits, 2),
            ];
        }

        return [
            'total_zwg' => round($amount, 2),
            'units' => round($unitsBought, 2),
            'energy_charge' => round($energyCharge, 2),
            're_levy' => round($reLevy, 2),
            'band_breakdown' => $output,
        ];
    }

    private function getTariffBands(): array
    {
        try {
            $rows = DB::table('tariff_bands')
                ->where('is_final_band', false)
                ->orderBy('band_order')
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows->map(function ($band) {
                    return [
                        'price' => (float)$band->price,
                        'limit' => (float)$band->unit_limit,
                        'remaining' => (float)$band->unit_limit,
                    ];
                })->toArray();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch tariff bands from DB, using defaults', [
                'error' => $e->getMessage(),
            ]);
        }

        return array_map(function ($band) {
            return [
                'price' => $band['price'],
                'limit' => $band['limit'],
                'remaining' => $band['limit'],
            ];
        }, self::DEFAULT_TARIFF_BANDS);
    }

    private function getFinalBandPrice(): float
    {
        try {
            $final = DB::table('tariff_bands')
                ->where('is_final_band', true)
                ->first();

            if ($final) {
                return (float)$final->price;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch final band price from DB, using default', [
                'error' => $e->getMessage(),
            ]);
        }

        return self::DEFAULT_FINAL_BAND_PRICE;
    }

    private function adjustBandsForPreviousUsage(array $bands, float $previousUnits): array
    {
        $remainingPrevious = $previousUnits;

        foreach ($bands as $key => $band) {
            if ($remainingPrevious <= 0) {
                break;
            }

            if ($remainingPrevious >= $band['remaining']) {
                $remainingPrevious -= $band['remaining'];
                $bands[$key]['remaining'] = 0;
            } else {
                $bands[$key]['remaining'] -= $remainingPrevious;
                $remainingPrevious = 0;
            }
        }

        return $bands;
    }
}
