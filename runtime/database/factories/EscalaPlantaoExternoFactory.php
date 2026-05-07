<?php

namespace Database\Factories;

use App\Models\EscalaPlantaoExterno;
use Illuminate\Database\Eloquent\Factories\Factory;

class EscalaPlantaoExternoFactory extends Factory
{
    protected $model = EscalaPlantaoExterno::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->word(),
            'sigla' => strtoupper($this->faker->unique()->lexify('PL?')),
            'is_active' => 1,
        ];
    }
}
