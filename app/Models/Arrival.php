<?php

namespace App\Models;

class Arrival
{
    public function __construct(
        public readonly string $routeName,
        public readonly string $routeDirectionName,
        public readonly int $etaMin,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routeName: $data['route_name'],
            routeDirectionName: $data['stations']['arrival'],
            etaMin: (int) $data['eta_min'],
        );
    }
}
