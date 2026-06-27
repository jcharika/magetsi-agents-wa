<?php

namespace App\Services\CustomerFlow\Support;

use Illuminate\Support\Facades\Log;

class ServiceNavBuilder
{
    private string $defaultMeterCurrency = 'ZWG';

    public function __construct(
        private PaymentMethodsBuilder $paymentMethodsBuilder,
    ) {}

    public function isServiceEnabled(string $service): bool
    {
        return (bool) config("whatsapp.customer_services.{$service}", true);
    }

    public function build(): array
    {
        $iconDir = public_path('images/service-icons');

        $serviceDefs = [
            'ZESA' => [
                'screen' => 'ZESA_SCREEN',
                'title' => 'Buy Electricity (ZESA)',
                'desc' => 'Prepaid tokens',
                'file' => 'zesa.png',
                'alt' => 'ZESA electricity',
                'payload' => [
                    'trigger' => 'init_zesa',
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'meter_currency' => $this->defaultMeterCurrency,
                    'ecocash_number' => '',
                    'payment_methods' => $this->paymentMethodsBuilder->build('ZWG'),
                ],
            ],
            'AIRTIME' => [
                'screen' => 'AIRTIME_SCREEN',
                'title' => 'Buy Airtime',
                'desc' => 'NetOne and Econet',
                'file' => 'airtime.png',
                'alt' => 'Airtime',
                'payload' => [
                    'trigger' => 'init_airtime',
                ],
            ],
            'BUNDLES' => [
                'screen' => 'BUNDLES_SCREEN',
                'title' => 'Buy Bundles',
                'desc' => 'Data for all nets',
                'file' => 'bundles.png',
                'alt' => 'Data bundles',
                'payload' => [
                    'trigger' => 'init_bundles',
                    'ecocash_number' => '',
                ],
            ],
            'TELONE' => [
                'screen' => 'TELONE_HOME_SCREEN',
                'title' => 'TelOne WiFi',
                'desc' => 'Broadband bundles',
                'file' => 'telone.png',
                'alt' => 'TelOne',
                'payload' => [
                    'trigger' => 'init_telone',
                ],
            ],
            'BILLERS' => [
                'screen' => 'BILLERS_SCREEN',
                'title' => 'Pay Bills',
                'desc' => 'Third-party billers',
                'file' => 'billers.png',
                'alt' => 'Billers',
                'payload' => [
                    'trigger' => 'init_billers',
                    'ecocash_number' => '',
                ],
            ],
            'GIFT_CARDS' => [
                'screen' => 'GIFT_CARDS_SCREEN',
                'title' => 'Buy Gift Cards',
                'desc' => 'Netflix, Roblox and more',
                'file' => 'gift-cards.png',
                'alt' => 'Gift Cards',
                'payload' => [
                    'trigger' => 'init_gift_cards',
                ],
            ],
            'CORPORATE_BILLS' => [
                'screen' => 'CORPORATE_BILLS_SCREEN',
                'title' => 'Corporate Bills',
                'desc' => 'Bulk bill payments',
                'file' => 'corporate.png',
                'alt' => 'Corporate Bill Payments',
                'payload' => [
                    'trigger' => 'init_corporate_bills',
                ],
            ],
            'ZESA_CALCULATOR' => [
                'screen' => 'ZESA_CALCULATOR_SCREEN',
                'title' => 'ZESA Calculator',
                'desc' => 'Estimate ZESA costs',
                'file' => 'zesa.png',
                'alt' => 'ZESA Calculator',
                'payload' => [
                    'trigger' => 'init_zesa_calculator',
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
            ],
            'SUPPORT' => [
                'screen' => 'SUPPORT_SCREEN',
                'title' => 'Contact Support',
                'desc' => 'Get help',
                'file' => 'support.png',
                'alt' => 'Support',
                'payload' => [
                    'trigger' => 'init_support',
                ],
            ],
        ];

        $items = [];
        foreach ($serviceDefs as $envSuffix => $svc) {
            if (!$this->isServiceEnabled($envSuffix)) continue;

            $iconPath = $iconDir . '/' . $svc['file'];
            $image = '';
            if (file_exists($iconPath)) {
                $image = base64_encode(file_get_contents($iconPath));
            }

            $items[] = [
                'id' => $svc['screen'],
                'start' => [
                    'image' => $image,
                    'alt-text' => $svc['alt'],
                ],
                'main-content' => [
                    'title' => $svc['title'],
                    'metadata' => $svc['desc'],
                ],
                'on-click-action' => [
                    'name' => 'navigate',
                    'next' => ['type' => 'screen', 'name' => $svc['screen']],
                    'payload' => $svc['payload'],
                ],
            ];
        }

        return $items;
    }

    public function serviceDisabledResponse(string $screen): ?array
    {
        $screenServiceMap = [
            'ZESA_SCREEN' => 'ZESA',
            'AIRTIME_SCREEN' => 'AIRTIME',
            'BUNDLES_SCREEN' => 'BUNDLES',
            'ECONET_USD_BUNDLE_SCREEN' => 'BUNDLES',
            'ECONET_ZWG_BUNDLE_SCREEN' => 'BUNDLES',
            'ECONET_SMARTSUITE_BUNDLE_SCREEN' => 'BUNDLES',
            'NETONE_USD_BUNDLE_SCREEN' => 'BUNDLES',
            'TELONE_HOME_SCREEN' => 'TELONE',
            'TELONE_SCREEN' => 'TELONE',
            'TELONE_USD_SCREEN' => 'TELONE',
            'BILLERS_SCREEN' => 'BILLERS',
            'SUPPORT_SCREEN' => 'SUPPORT',
            'GIFT_CARDS_SCREEN' => 'SUPPORT',
            'CORPORATE_BILLS_SCREEN' => 'SUPPORT',
            'ZESA_CALCULATOR_SCREEN' => 'ZESA_CALCULATOR',
        ];

        $service = $screenServiceMap[$screen] ?? null;
        if ($service && !$this->isServiceEnabled($service)) {
            Log::debug("Flow: service '{$service}' disabled, redirecting to HOME_SCREEN");
            return [
                'screen' => 'HOME_SCREEN',
                'data' => [
                    'error_message' => "This service is currently unavailable.",
                    'enabled_services' => $this->build(),
                ],
            ];
        }
        return null;
    }
}
