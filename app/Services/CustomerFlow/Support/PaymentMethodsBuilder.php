<?php

namespace App\Services\CustomerFlow\Support;

class PaymentMethodsBuilder
{
    public function build(string $currency): array
    {
        $methods = [
            ['id' => 'ecocash-usd', 'title' => 'EcoCash USD'],
        ];
        if ($currency !== 'USD') {
            $methods[] = ['id' => 'ecocash', 'title' => 'EcoCash ZWG'];
        }
        $methods[] = ['id' => 'stripe', 'title' => 'International Card'];
        return $methods;
    }
}
