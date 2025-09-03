<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->generateProductName();
        $productNumber = $this->generateProductNumber();
        $title = $this->generateProductTitle($name);
        $mfPnum = $this->faker->optional()->bothify('MPN-####');

        // Create required statuses first
        $inactiveStatus = Status::updateOrCreate([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'description' => 'Active status',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $activeStatus = Status::updateOrCreate([
            'name' => 'Active',
            'slug' => 'active',
            'description' => 'Inactive status',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        return [
            'name' => $name,
            'title' => $title,
            'pnum' => $productNumber,
            'mf_pnum' => $mfPnum,
            'mf_pnum_slug' => $mfPnum ? Str::slug($mfPnum) : null,
            'description' => $this->faker->paragraphs(3, true),
            'category_id' => null, // Will be set via relationships
            'category_name' => null, // Will be populated by triggers
            'manufacturer_id' => null, // Will be set via relationships
            'manufacturer_name' => null, // Will be populated by triggers
            'brand_id' => null, // Will be set via relationships
            'brand_name' => null, // Will be populated by triggers
            'breadcrumb' => $this->generateBreadcrumb('Category', $name),
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->sentence(12),
            'is_rohs_compliant' => $this->faker->boolean(70),
            'pushed' => $this->faker->boolean(60),
            'total_reviews' => $this->faker->numberBetween(0, 500),
            'average_rating' => $this->faker->optional()->randomFloat(1, 1, 5),
            'video_url' => $this->faker->optional()->url(),
            'status_id' => $activeStatus->id, // 80% active, 20% inactive
            'is_updated' => $this->faker->boolean(30),
            'sess_insrt_id' => $this->faker->randomNumber(5),
            'sess_updt_id' => $this->faker->randomNumber(5),
        ];
    }

    public function solarPanel(): static
    {
        return $this->state(function () {
            $types = ['Monocrystalline', 'Polycrystalline', 'Thin Film', 'Bifacial', 'PERC'];
            $brands = ['SunPower', 'Tesla', 'LG Solar', 'Canadian Solar', 'Jinko Solar'];
            $wattages = [300, 350, 400, 450, 500, 550, 600];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $wattage = $this->faker->randomElement($wattages);
            $model = $this->faker->bothify('SP-###');

            return [
                'name' => "{$brand} {$wattage}W {$type} Solar Panel - Model {$model}",
                'description' => $this->generateSolarPanelDescription($type, $wattage),
            ];
        });
    }

    public function windTurbine(): static
    {
        return $this->state(function () {
            $types = ['Horizontal Axis', 'Vertical Axis', 'Offshore', 'Small Wind', 'Micro Wind'];
            $brands = ['Vestas', 'Siemens Gamesa', 'GE Renewable', 'Goldwind', 'Enercon'];
            $capacities = [1.5, 2.0, 2.5, 3.0, 4.0, 6.0, 8.0, 12.0];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $capacity = $this->faker->randomElement($capacities);
            $model = $this->faker->bothify('WT-###');

            return [
                'name' => "{$brand} {$capacity}MW {$type} Wind Turbine - Model {$model}",
                'description' => $this->generateWindTurbineDescription($type, $capacity),
            ];
        });
    }

    public function batteryStorage(): static
    {
        return $this->state(function () {
            $types = ['Lithium-ion', 'Lead-acid', 'Flow Battery', 'Sodium-ion', 'Solid State'];
            $brands = ['Tesla Powerwall', 'LG Chem', 'Sonnen', 'Enphase', 'BYD', 'Generac PWRcell'];
            $capacities = [5, 7, 10, 13.5, 16, 20, 25, 30];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $capacity = $this->faker->randomElement($capacities);
            $model = $this->faker->bothify('BS-###');

            return [
                'name' => "{$brand} {$capacity}kWh {$type} Battery Storage - Model {$model}",
                'description' => $this->generateBatteryStorageDescription($type, $capacity),
            ];
        });
    }

    public function inverter(): static
    {
        return $this->state(function () {
            $types = ['String Inverter', 'Power Optimizer', 'Microinverter', 'Central Inverter', 'Hybrid Inverter'];
            $brands = ['SolarEdge', 'Enphase', 'SMA', 'Fronius', 'Huawei', 'ABB'];
            $capacities = [3, 5, 7.5, 10, 15, 20, 25, 30, 50, 100];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $capacity = $this->faker->randomElement($capacities);
            $model = $this->faker->bothify('INV-###');

            return [
                'name' => "{$brand} {$capacity}kW {$type} - Model {$model}",
                'description' => $this->generateInverterDescription($type, $capacity),
            ];
        });
    }

    public function energyMonitoring(): static
    {
        return $this->state(function () {
            $types = ['Smart Meter', 'Energy Monitor', 'Power Analyzer', 'Load Monitor', 'Grid Tie Monitor'];
            $brands = ['Sense', 'Emporia Vue', 'Schneider Electric', 'Siemens', 'ABB', 'Fluke'];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $model = $this->faker->bothify('EM-###');

            return [
                'name' => "{$brand} {$type} - Model {$model}",
                'description' => $this->generateEnergyMonitoringDescription($type),
            ];
        });
    }

    public function electricalFuse(): static
    {
        return $this->state(function () {
            $amperages = ['5A', '10A', '15A', '20A', '25A', '30A', '40A', '50A'];
            $voltages = ['125V', '250V', '600V'];
            $types = ['Fast-Acting', 'Time-Delay', 'Current-Limiting'];

            $amperage = $this->faker->randomElement($amperages);
            $voltage = $this->faker->randomElement($voltages);
            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement(['Bussmann', 'Littelfuse', 'Ferraz Shawmut', 'Eaton']);

            return [
                'name' => "{$brand} {$amperage} {$voltage} {$type} Fuse",
                'description' => $this->generateFuseDescription($amperage, $voltage, $type),
            ];
        });
    }

    public function screwdriver(): static
    {
        return $this->state(function () {
            $types = ['Phillips Head', 'Flathead', 'Torx', 'Robertson', 'Precision'];
            $brands = ['Stanley', 'Klein Tools', 'Craftsman', 'Snap-on', 'Wera'];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $size = $this->faker->randomElement(['#0', '#1', '#2', '#3', '1/4"', '3/16"']);

            return [
                'name' => "{$brand} {$type} Screwdriver {$size}",
                'description' => $this->generateScrewdriverDescription($type),
            ];
        });
    }

    public function saw(): static
    {
        return $this->state(function () {
            $types = ['Circular Saw', 'Jigsaw', 'Reciprocating Saw', 'Miter Saw', 'Band Saw'];
            $brands = ['DeWalt', 'Milwaukee', 'Makita', 'Bosch', 'Ridgid'];

            $type = $this->faker->randomElement($types);
            $brand = $this->faker->randomElement($brands);
            $model = $this->faker->bothify('###??');

            return [
                'name' => "{$brand} {$type} - Model {$model}",
                'description' => $this->generateSawDescription($type),
            ];
        });
    }

    private function generateProductName(): string
    {
        $brands = ['ProTool', 'MaxForce', 'TechCraft', 'PowerMax', 'ElectroPlus'];
        $descriptors = ['Professional', 'Heavy Duty', 'Premium', 'Industrial', 'Commercial'];
        $tools = ['Tool', 'Component', 'Equipment', 'Device', 'Instrument'];

        return $this->faker->randomElement($brands).' '.
               $this->faker->randomElement($descriptors).' '.
               $this->faker->randomElement($tools);
    }

    private function generateProductNumber(): string
    {
        return $this->faker->unique()->bothify('HT######');
    }

    private function generateProductTitle(string $name): string
    {
        return $name.' - '.$this->faker->words(2, true);
    }

    private function generateBreadcrumb(string $categoryName, string $productName): string
    {
        return "Home > {$categoryName} > ".Str::limit($productName, 30);
    }

    // Image generation methods moved to ProductImageFactory

    private function generateSolarPanelAttributes(string $type, int $wattage): array
    {
        return [
            'technology' => $type,
            'power_output' => $wattage.'W',
            'efficiency' => $this->faker->randomFloat(1, 18.5, 22.8).'%',
            'voltage_max_power' => $this->faker->randomFloat(1, 30, 40).'V',
            'current_max_power' => $this->faker->randomFloat(2, 8, 12).'A',
            'open_circuit_voltage' => $this->faker->randomFloat(1, 35, 48).'V',
            'short_circuit_current' => $this->faker->randomFloat(2, 9, 13).'A',
            'operating_temperature' => '-40°C to +85°C',
            'dimensions' => $this->faker->randomElement(['2000x1000x35mm', '1950x992x40mm', '2108x1048x35mm']),
            'weight' => $this->faker->randomFloat(1, 18, 25).'kg',
            'warranty' => $this->faker->randomElement(['25 years', '20 years', '15 years']),
            'certification' => 'IEC 61215, IEC 61730, UL 1703',
            'frame_material' => 'Anodized Aluminum',
            'junction_box' => 'IP67 Rated',
        ];
    }

    private function generateWindTurbineAttributes(string $type, float $capacity): array
    {
        return [
            'turbine_type' => $type,
            'rated_power' => $capacity.'MW',
            'rotor_diameter' => $this->faker->randomFloat(1, 80, 150).'m',
            'hub_height' => $this->faker->randomFloat(0, 80, 120).'m',
            'cut_in_wind_speed' => $this->faker->randomFloat(1, 3, 4).' m/s',
            'rated_wind_speed' => $this->faker->randomFloat(1, 12, 15).' m/s',
            'cut_out_wind_speed' => $this->faker->randomFloat(0, 20, 25).' m/s',
            'number_of_blades' => $this->faker->randomElement([2, 3]),
            'gearbox_type' => $this->faker->randomElement(['Planetary', 'Helical', 'Direct Drive']),
            'generator_type' => $this->faker->randomElement(['DFIG', 'PMSG', 'SCIG']),
            'control_system' => 'Pitch Control',
            'grid_connection' => $this->faker->randomElement(['690V', '1000V', '1500V']),
            'certification' => 'IEC 61400-1, IEC 61400-22',
            'design_life' => '20 years',
            'operating_temperature' => '-30°C to +50°C',
        ];
    }

    private function generateBatteryStorageAttributes(string $type, float $capacity): array
    {
        return [
            'battery_type' => $type,
            'usable_capacity' => $capacity.'kWh',
            'total_capacity' => ($capacity * 1.1).'kWh',
            'voltage_nominal' => $this->faker->randomElement(['48V', '400V', '800V']),
            'max_charge_power' => $this->faker->randomFloat(1, 3, 10).'kW',
            'max_discharge_power' => $this->faker->randomFloat(1, 3, 10).'kW',
            'round_trip_efficiency' => $this->faker->randomFloat(1, 90, 96).'%',
            'depth_of_discharge' => $this->faker->randomElement(['80%', '90%', '95%', '100%']),
            'cycle_life' => $this->faker->randomElement(['6000', '8000', '10000']),
            'operating_temperature' => '-10°C to +50°C',
            'dimensions' => $this->faker->randomElement(['1150x755x155mm', '1200x800x200mm']),
            'weight' => $this->faker->randomFloat(1, 100, 300).'kg',
            'warranty' => $this->faker->randomElement(['10 years', '15 years', '20 years']),
            'protection_rating' => 'IP65',
            'communication' => 'Ethernet, Wi-Fi, CAN Bus',
        ];
    }

    private function generateInverterAttributes(string $type, float $capacity): array
    {
        return [
            'inverter_type' => $type,
            'ac_power_rating' => $capacity.'kW',
            'dc_input_voltage' => $this->faker->randomElement(['600V', '1000V', '1500V']),
            'ac_output_voltage' => $this->faker->randomElement(['230V', '400V', '480V']),
            'efficiency' => $this->faker->randomFloat(1, 95, 99).'%',
            'maximum_dc_input' => ($capacity * 1.3).'kW',
            'mppt_trackers' => $this->faker->randomElement([1, 2, 3, 4]),
            'grid_connection' => 'Three-phase',
            'communication' => 'Ethernet, Wi-Fi, RS485',
            'monitoring' => 'Web Portal, Mobile App',
            'protection_rating' => 'IP65',
            'operating_temperature' => '-25°C to +60°C',
            'dimensions' => $this->faker->randomElement(['665x445x244mm', '800x600x300mm']),
            'weight' => $this->faker->randomFloat(1, 25, 80).'kg',
            'certification' => 'IEC 62109, UL 1741',
            'warranty' => $this->faker->randomElement(['10 years', '15 years', '20 years']),
        ];
    }

    private function generateEnergyMonitoringAttributes(string $type): array
    {
        return [
            'device_type' => $type,
            'measurement_accuracy' => $this->faker->randomElement(['±0.5%', '±1%', '±2%']),
            'sampling_rate' => $this->faker->randomElement(['1 Hz', '10 Hz', '60 Hz']),
            'voltage_range' => '80V-600V',
            'current_range' => '5A-6000A',
            'power_measurement' => 'Active, Reactive, Apparent',
            'communication' => $this->faker->randomElement(['Wi-Fi', 'Ethernet', 'Zigbee', 'LoRa']),
            'display' => $this->faker->randomElement(['LCD', 'LED', 'Mobile App Only']),
            'data_logging' => 'Cloud Storage',
            'operating_temperature' => '-20°C to +70°C',
            'protection_rating' => 'IP54',
            'certification' => 'CE, FCC, UL',
            'warranty' => $this->faker->randomElement(['2 years', '3 years', '5 years']),
            'installation' => $this->faker->randomElement(['DIN Rail', 'Wall Mount', 'Panel Mount']),
        ];
    }

    private function generateSolarPanelDescription(string $type, int $wattage): string
    {
        return "High-efficiency {$wattage}W {$type} solar panel designed for residential and commercial installations. Features advanced cell technology with superior light absorption and excellent low-light performance. Weather-resistant construction ensures reliable operation in various environmental conditions. Perfect for grid-tied and off-grid solar energy systems.";
    }

    private function generateWindTurbineDescription(string $type, float $capacity): string
    {
        return "Advanced {$capacity}MW {$type} wind turbine engineered for optimal energy production. Features state-of-the-art aerodynamic design and intelligent control systems for maximum efficiency. Built to withstand harsh weather conditions with minimal maintenance requirements. Ideal for utility-scale and commercial wind energy projects.";
    }

    private function generateBatteryStorageDescription(string $type, float $capacity): string
    {
        return "Advanced {$capacity}kWh {$type} battery storage system designed for residential and commercial energy storage applications. Features intelligent energy management with seamless grid integration. High cycle life and fast charging capabilities ensure optimal performance and longevity. Perfect for solar energy storage and backup power solutions.";
    }

    private function generateInverterDescription(string $type, float $capacity): string
    {
        return "High-efficiency {$capacity}kW {$type} designed for solar PV installations. Features advanced MPPT technology for maximum energy harvest and grid-tie capabilities. Intelligent monitoring and communication systems provide real-time performance data. Built for reliability with comprehensive protection features and long service life.";
    }

    private function generateEnergyMonitoringDescription(string $type): string
    {
        return "Professional {$type} designed for comprehensive energy monitoring and management. Features high-accuracy measurement capabilities with real-time data logging and analysis. Advanced communication options enable remote monitoring and integration with energy management systems. Essential for energy efficiency optimization and demand response programs.";
    }

    private function generateFuseDescription(string $amperage, string $voltage, string $type): string
    {
        return "High-quality {$type} electrical fuse rated at {$amperage} and {$voltage}. Designed for reliable circuit protection with superior interrupting capacity. UL listed for safety and compliance. Suitable for industrial, commercial, and residential electrical applications. Ensures proper overcurrent protection and electrical safety.";
    }

    private function generateScrewdriverDescription(string $type): string
    {
        return "Professional grade {$type} screwdriver designed for heavy-duty use. Features ergonomic handle design for comfort during extended use. Made from high-quality chrome vanadium steel for durability and long service life. Perfect for professional tradespeople and serious DIY enthusiasts. Precision-machined tips ensure proper fit and reduce cam-out.";
    }

    private function generateSawDescription(string $type): string
    {
        return "High-performance {$type} engineered for professional applications. Features powerful motor and precision-engineered components for accurate cuts. Includes advanced safety features and ergonomic design for user comfort. Ideal for construction, woodworking, and industrial applications. Built to withstand demanding job site conditions.";
    }

    public function withRelationships($categoryId = null, $manufacturerId = null): static
    {
        return $this->state(function (array $attributes) use ($categoryId, $manufacturerId) {
            $state = [];

            if ($categoryId !== null) {
                $category = Category::find($categoryId);
                $state['category_id'] = $categoryId;
                $state['category_name'] = $category->name;
                $state['breadcrumb'] = $this->generateBreadcrumb($category->name, $attributes['name']);
            }

            if ($manufacturerId !== null) {
                $manufacturer = Manufacturer::find($manufacturerId);
                $state['manufacturer_id'] = $manufacturerId;
                $state['manufacturer_name'] = $manufacturer->name;
            }

            return $state;
        });
    }
}
