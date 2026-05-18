<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Run order matters — later seeders depend on earlier ones.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,             // 1. Users (needed as FK for assignments & receivals)
            DepartmentSeeder::class,       // 2. Departments
            CategorySeeder::class,         // 3. Categories (parents seeded before children)
            UnitSeeder::class,             // 4. Units of measurement
            SupplierSeeder::class,         // 5. Suppliers
            ItemSeeder::class,             // 6. Item definitions (fixed_asset & consumable)
            EmployeeSeeder::class,         // 7. Employees (requires departments)
            ItemAssetSeeder::class,        // 8. Individual fixed-asset units + initial assignments
            InventoryStockSeeder::class,   // 9. Consumable stocks + stock receival records
            DepartmentUserSeeder::class,   // 10. One user account per department
            StockIssuanceSeeder::class,    // 11. Sample stock issuances (decrements stock)
            RoomInventorySeeder::class,    // 12. Room locations + furniture assets (from NDB Excel reference)
            FdcRoomInventorySeeder::class, // 13. FDC location + furniture assets (from FDC Excel reference)
            CdcRoomInventorySeeder::class, // 14. CDC locations + furniture assets (from CDC Excel reference)
        ]);
    }
}
