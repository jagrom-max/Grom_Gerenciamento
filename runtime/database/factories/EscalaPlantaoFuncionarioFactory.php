<?php

namespace Database\Factories;

use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use App\Models\EscalaPlantaoExterno;
use Illuminate\Database\Eloquent\Factories\Factory;

class EscalaPlantaoFuncionarioFactory extends Factory
{
    protected $model = EscalaPlantaoFuncionario::class;

    public function definition(): array
    {
        return [
            'data' => $this->faker->date(),
            'funcionario_id' => RhFuncionario::factory(),
            'plantao_externo_id' => EscalaPlantaoExterno::factory(),
        ];
    }
}
