<?php

namespace App\Services\CustomerFlow\Contracts;

use App\Models\Customer;

interface ScreenActionInterface
{
    public function handledScreens(): array;

    public function init(string $screen, array $data, Customer $customer): array;

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array;
}
