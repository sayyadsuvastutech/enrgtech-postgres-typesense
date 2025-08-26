<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    private static array $sources = ['dk', 'rs', 'ct', 'vp', 'et'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'source_name' => $this->faker->randomElement(self::$sources),
            'attributes' => $this->generateAttributesData(),
        ];
    }

    private function generateAttributesData(): array
    {
        // Generate attributes as direct key-value pairs in the JSON object
        return $this->generateAttributes();
    }

    private function generateAttributes(): array
    {
        // Generate mixed energy technology and traditional product attributes as key-value pairs
        $allAttributes = [
            'technology_type' => $this->faker->randomElement(['Solar', 'Wind', 'Battery Storage', 'Inverter', 'Energy Monitoring', 'Tool', 'Electrical Component']),
            'efficiency' => $this->faker->randomFloat(1, 85, 99).'%',
            'power_rating' => $this->faker->randomFloat(1, 1, 1000).'kW',
            'voltage_rating' => $this->faker->randomElement(['12V', '24V', '48V', '120V', '240V', '400V', '690V', '1000V']),
            'current_rating' => $this->faker->randomElement(['5A', '10A', '15A', '20A', '30A', '50A']),
            'operating_temperature' => $this->faker->randomElement(['-40°C to +85°C', '-25°C to +60°C', '-10°C to +50°C']),
            'protection_rating' => $this->faker->randomElement(['IP54', 'IP65', 'IP67']),
            'certification' => $this->faker->randomElement(['IEC 61215', 'UL 1741', 'UL Listed', 'CE', 'FCC', 'RoHS']),
            'warranty_years' => $this->faker->randomElement([1, 2, 3, 5, 10, 15, 20, 25]),
            'material' => $this->faker->randomElement(['Steel', 'Aluminum', 'Plastic', 'Ceramic', 'Chrome Vanadium']),
            'dimensions' => $this->faker->randomFloat(0, 50, 2000).'x'.$this->faker->randomFloat(0, 50, 1500).'x'.$this->faker->randomFloat(0, 10, 200).'mm',
            'weight' => $this->faker->randomFloat(1, 0.1, 500).$this->faker->randomElement(['kg', 'lbs']),
            'communication_protocol' => $this->faker->randomElement(['Wi-Fi', 'Ethernet', 'RS485', 'CAN Bus', 'Modbus']),
        ];

        // Return a subset of attributes
        return array_slice($allAttributes, 0, $this->faker->numberBetween(5, 8), true);
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }

    public function solarPanel(): static
    {
        return $this->state(function () {
            return [
                'attributes' => [
                    'technology' => $this->faker->randomElement(['Monocrystalline', 'Polycrystalline', 'Thin Film']),
                    'power_output' => $this->faker->randomElement([300, 350, 400, 450, 500]).'W',
                    'efficiency' => $this->faker->randomFloat(1, 18.5, 22.8).'%',
                    'voltage_max_power' => $this->faker->randomFloat(1, 30, 40).'V',
                    'current_max_power' => $this->faker->randomFloat(2, 8, 12).'A',
                    'operating_temperature' => '-40°C to +85°C',
                    'dimensions' => '2000x1000x35mm',
                    'weight' => $this->faker->randomFloat(1, 18, 25).'kg',
                    'warranty' => $this->faker->randomElement([20, 25]).' years',
                    'certification' => 'IEC 61215, IEC 61730',
                ],
            ];
        });
    }

    public function windTurbine(): static
    {
        return $this->state(function () {
            return [
                'attributes' => [
                    'turbine_type' => $this->faker->randomElement(['Horizontal Axis', 'Vertical Axis']),
                    'rated_power' => $this->faker->randomElement([1.5, 2.0, 2.5, 3.0, 4.0]).'MW',
                    'rotor_diameter' => $this->faker->randomFloat(1, 80, 150).'m',
                    'hub_height' => $this->faker->randomFloat(0, 80, 120).'m',
                    'cut_in_wind_speed' => $this->faker->randomFloat(1, 3, 4).' m/s',
                    'rated_wind_speed' => $this->faker->randomFloat(1, 12, 15).' m/s',
                    'number_of_blades' => $this->faker->randomElement([2, 3]),
                    'generator_type' => $this->faker->randomElement(['DFIG', 'PMSG', 'SCIG']),
                    'certification' => 'IEC 61400-1, IEC 61400-22',
                    'design_life' => '20 years',
                ],
            ];
        });
    }

    public function batteryStorage(): static
    {
        return $this->state(function () {
            return [
                'attributes' => [
                    'battery_type' => $this->faker->randomElement(['Lithium-ion', 'Lead-acid', 'Flow Battery']),
                    'usable_capacity' => $this->faker->randomElement([5, 7, 10, 13.5, 16, 20]).'kWh',
                    'voltage_nominal' => $this->faker->randomElement(['48V', '400V', '800V']),
                    'max_charge_power' => $this->faker->randomFloat(1, 3, 10).'kW',
                    'max_discharge_power' => $this->faker->randomFloat(1, 3, 10).'kW',
                    'round_trip_efficiency' => $this->faker->randomFloat(1, 90, 96).'%',
                    'cycle_life' => $this->faker->randomElement(['6000', '8000', '10000']),
                    'operating_temperature' => '-10°C to +50°C',
                    'protection_rating' => 'IP65',
                    'warranty' => $this->faker->randomElement([10, 15, 20]).' years',
                ],
            ];
        });
    }

    public function electricalFuse(): static
    {
        return $this->state(function () {
            return [
                'attributes' => [
                    'amperage' => $this->faker->randomElement(['5A', '10A', '15A', '20A', '30A', '40A']),
                    'voltage_rating' => $this->faker->randomElement(['125V', '250V', '600V']),
                    'fuse_type' => $this->faker->randomElement(['Fast-Acting', 'Time-Delay', 'Current-Limiting']),
                    'material' => 'Ceramic/Glass',
                    'mounting_type' => $this->faker->randomElement(['Panel Mount', 'Fuse Block', 'Inline']),
                    'interrupting_rating' => $this->faker->randomElement(['10kA', '100kA', '200kA']),
                    'operating_temperature' => '-40°C to +85°C',
                    'certification' => 'UL Listed, CSA Certified',
                ],
            ];
        });
    }

    public function screwdriver(): static
    {
        return $this->state(function () {
            return [
                'attributes' => [
                    'screwdriver_type' => $this->faker->randomElement(['Phillips', 'Flathead', 'Torx', 'Robertson']),
                    'tip_size' => $this->faker->randomElement(['#0', '#1', '#2', '#3', '1/4"', '3/16"']),
                    'handle_material' => $this->faker->randomElement(['Rubber Grip', 'Plastic', 'Cushion Grip']),
                    'shaft_material' => 'Chrome Vanadium Steel',
                    'length' => $this->faker->randomElement(['4"', '6"', '8"', '10"']),
                    'magnetic_tip' => $this->faker->boolean() ? 'Yes' : 'No',
                    'finish' => $this->faker->randomElement(['Chrome', 'Black Oxide', 'Zinc Plated']),
                    'warranty' => $this->faker->randomElement(['Lifetime', '1 Year', '5 Years']),
                ],
            ];
        });
    }

    public function saw(): static
    {
        return $this->state(function () {
            return [
                'attributes' => [
                    'saw_type' => $this->faker->randomElement(['Circular', 'Jigsaw', 'Reciprocating', 'Miter', 'Band']),
                    'blade_diameter' => $this->faker->randomElement(['7-1/4"', '8-1/4"', '10"', '12"']),
                    'motor_power' => $this->faker->randomElement(['10A', '13A', '15A']),
                    'cutting_depth' => $this->faker->randomFloat(1, 2.0, 4.0).'"',
                    'bevel_capacity' => $this->faker->randomElement(['45°', '50°', '56°']),
                    'weight' => $this->faker->randomFloat(1, 8.0, 25.0).' lbs',
                    'cord_length' => $this->faker->randomElement(['6 ft', '8 ft', '10 ft']),
                    'laser_guide' => $this->faker->boolean() ? 'Yes' : 'No',
                ],
            ];
        });
    }
}
