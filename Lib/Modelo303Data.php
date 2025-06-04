<?php

namespace FacturaScripts\Plugins\Modelo303\Lib;

class Modelo303Data
{
    private array $data = [];
    private const DEFAULT_RATES = [
        '02' => 4.00,
        '05' => 10.00,
        '08' => 21.00,
        '157' => 1.75,
        '169' => 0.5,
        '20' => 1.4,
        '23' => 5.2
    ];

    public function __construct()
    {
        $this->initialize();
    }

    private function initialize(): void
    {
        for ($i = 0; $i <= 200; $i++) {
            $this->data[sprintf('%02d', $i)] = 0.00;
        }

        foreach (self::DEFAULT_RATES as $key => $rate) {
            $this->data[$key] = $rate;
        }
    }

    public function get(string $key): float
    {
        return $this->data[$key] ?? 0.00;
    }

    public function set(string $key, float $value): void
    {
        $this->data[$key] = $value;
    }

    public function add(string $key, float $value): void
    {
        $this->data[$key] = ($this->data[$key] ?? 0.00) + $value;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
