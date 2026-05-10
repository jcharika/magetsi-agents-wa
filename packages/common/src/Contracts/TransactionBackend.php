<?php

namespace Magetsi\Common\Contracts;

interface TransactionBackend
{
    public function validateMeter(string $meterNumber): array;
    public function processTransaction(array $params): array;
    public function getBackendName(): string;
}
