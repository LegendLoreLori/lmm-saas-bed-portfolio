<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'start'=> $this->faker->dateTimeBetween('now')->format(\DateTimeInterface::ATOM),
            'end'=> $this->faker->dateTimeBetween('+1 month', '+3 months')->format(\DateTimeInterface::ATOM),
            'title'=> $this->faker->jobTitle(),
            'description'=> $this->faker->sentence(),
            'min_salary'=> $this->faker->numberBetween(40000,80000),
            'max_salary'=> $this->faker->numberBetween(90000,150000),
            'currency'=> $this->faker->currencyCode(),
            'benefits'=> $this->faker->words(5, true),
            'requirements'=> $this->faker->words(10, true),
            'type'=> $this->faker->randomElement(['Casual', 'Part-Time', 'Full-Time', 'Contract']),
        ];
    }
}
