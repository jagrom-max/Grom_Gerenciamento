<?php

namespace Database\Factories;

use App\Models\RhFuncionario;
use App\Models\RhCargo;
use Illuminate\Database\Eloquent\Factories\Factory;

class RhFuncionarioFactory extends Factory
{
    protected $model = RhFuncionario::class;

    public function definition(): array
    {
        return [
            'matricula' => $this->faker->unique()->numerify('FUN-###'),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'cargo_id' => RhCargo::query()->value('id') ?? RhCargo::query()->create([
                'code' => 'RH-001',
                'name' => 'Escrivao',
                'is_active' => true,
            ])->id,
            'concorre_escala' => 1,
            'admission_date' => $this->faker->date(),
            'departure_date' => null,
            'notes' => null,
            'is_active' => 1,
        ];
    }
}
