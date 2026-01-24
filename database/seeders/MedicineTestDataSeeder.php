<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Supplier;
use App\Models\StockMovement;
use Carbon\Carbon;

class MedicineTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a sample supplier
        $supplier = Supplier::create([
            'name' => 'PharmaCorp Ltd',
            'contact_person' => 'John Doe',
            'email' => 'contact@pharmacorp.co.ke',
            'phone' => '+254712345678',
            'address' => '123 Medical Plaza',
            'city' => 'Nairobi',
            'country' => 'Kenya',
            'is_active' => true,
        ]);

        // Create medicines with batches
        $medicines = [
            [
                'name' => 'Paracetamol 500mg',
                'generic_name' => 'Acetaminophen',
                'barcode' => 'PAR500001',
                'category' => 'Tablet',
                'description' => 'Pain and fever relief medication',
                'manufacturer' => 'PharmaCorp Ltd',
                'unit_price' => 50.00,
                'cost_price' => 30.00,
                'reorder_level' => 100,
                'unit_of_measure' => 'piece',
                'batch' => [
                    'batch_number' => 'BATCH001',
                    'quantity' => 500,
                    'expiry_date' => Carbon::now()->addYear(),
                    'manufacture_date' => Carbon::now()->subMonths(2),
                    'cost_price_per_unit' => 30.00,
                    'selling_price_per_unit' => 50.00,
                ]
            ],
            [
                'name' => 'Amoxicillin 250mg',
                'generic_name' => 'Amoxicillin',
                'barcode' => 'AMO250001',
                'category' => 'Capsule',
                'description' => 'Antibiotic for bacterial infections',
                'manufacturer' => 'Antibiotics Inc',
                'unit_price' => 80.00,
                'cost_price' => 50.00,
                'reorder_level' => 50,
                'unit_of_measure' => 'piece',
                'requires_prescription' => true,
                'batch' => [
                    'batch_number' => 'BATCH002',
                    'quantity' => 30, // Below reorder level
                    'expiry_date' => Carbon::now()->addMonths(6),
                    'manufacture_date' => Carbon::now()->subMonths(1),
                    'cost_price_per_unit' => 50.00,
                    'selling_price_per_unit' => 80.00,
                ]
            ],
            [
                'name' => 'Cough Syrup 100ml',
                'generic_name' => 'Dextromethorphan',
                'category' => 'Syrup',
                'description' => 'Relief for cough and cold symptoms',
                'manufacturer' => 'CoughCare Ltd',
                'unit_price' => 120.00,
                'cost_price' => 70.00,
                'reorder_level' => 20,
                'unit_of_measure' => 'bottle',
                'batch' => [
                    'batch_number' => 'BATCH003',
                    'quantity' => 50,
                    'expiry_date' => Carbon::now()->addDays(25), // Expiring soon
                    'manufacture_date' => Carbon::now()->subMonths(10),
                    'cost_price_per_unit' => 70.00,
                    'selling_price_per_unit' => 120.00,
                ]
            ],
            [
                'name' => 'Aspirin 100mg',
                'generic_name' => 'Acetylsalicylic Acid',
                'barcode' => 'ASP100001',
                'category' => 'Tablet',
                'description' => 'Blood thinner and pain relief',
                'manufacturer' => 'CardioMed',
                'unit_price' => 35.00,
                'cost_price' => 20.00,
                'reorder_level' => 150,
                'unit_of_measure' => 'piece',
                'batch' => [
                    'batch_number' => 'BATCH004',
                    'quantity' => 300,
                    'expiry_date' => Carbon::now()->addMonths(18),
                    'manufacture_date' => Carbon::now()->subMonths(1),
                    'cost_price_per_unit' => 20.00,
                    'selling_price_per_unit' => 35.00,
                ]
            ],
            [
                'name' => 'Ibuprofen 400mg',
                'generic_name' => 'Ibuprofen',
                'barcode' => 'IBU400001',
                'category' => 'Tablet',
                'description' => 'Anti-inflammatory pain reliever',
                'manufacturer' => 'PainRelief Co',
                'unit_price' => 60.00,
                'cost_price' => 35.00,
                'reorder_level' => 80,
                'unit_of_measure' => 'piece',
                'batch' => [
                    'batch_number' => 'BATCH005',
                    'quantity' => 200,
                    'expiry_date' => Carbon::now()->addYear(2),
                    'manufacture_date' => Carbon::now()->subWeeks(2),
                    'cost_price_per_unit' => 35.00,
                    'selling_price_per_unit' => 60.00,
                ]
            ],
        ];

        foreach ($medicines as $medicineData) {
            $batchData = $medicineData['batch'];
            unset($medicineData['batch']);

            // Create medicine
            $medicine = Medicine::create($medicineData);

            // Create batch
            $batch = MedicineBatch::create([
                'medicine_id' => $medicine->id,
                'batch_number' => $batchData['batch_number'],
                'quantity' => $batchData['quantity'],
                'initial_quantity' => $batchData['quantity'],
                'cost_price_per_unit' => $batchData['cost_price_per_unit'],
                'selling_price_per_unit' => $batchData['selling_price_per_unit'],
                'manufacture_date' => $batchData['manufacture_date'],
                'expiry_date' => $batchData['expiry_date'],
                'received_date' => now(),
                'supplier_id' => $supplier->id,
            ]);

            // Create stock movement
            StockMovement::create([
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'type' => 'purchase',
                'quantity' => $batchData['quantity'],
                'balance_after' => $batchData['quantity'],
                'unit_price' => $batchData['cost_price_per_unit'],
                'notes' => 'Initial stock',
                'user_id' => 1, // Owner user
            ]);
        }

        $this->command->info('Created ' . count($medicines) . ' medicines with batches');
    }
}
