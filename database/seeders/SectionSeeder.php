<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // ✅ SỬA ĐỔI: Tạm thời vô hiệu hóa kiểm tra khóa ngoại để có thể truncate
        Schema::disableForeignKeyConstraints();

        // Xóa dữ liệu cũ để tránh trùng lặp
        DB::table('sections')->truncate();

        // Kích hoạt lại kiểm tra khóa ngoại ngay sau khi truncate
        Schema::enableForeignKeyConstraints();

        // Chèn dữ liệu mới theo cấu trúc được yêu cầu
        DB::table('sections')->insert([
            ['name' => 'Accounting 1', 'code' => 'ACC1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Accounting 2 (HY)', 'code' => 'ACC2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Accounting 3 (VP)', 'code' => 'ACC3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Assembling (VP)', 'code' => 'ASM-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Business Planning', 'code' => 'BP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Casting 1', 'code' => 'CST1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Casting 2', 'code' => 'CST2', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Casting 3 (HY)', 'code' => 'CST3-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Casting 4 (HY)', 'code' => 'CST4-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Casting & Plating (VP)', 'code' => 'CP-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Customer Service (North & Middle)', 'code' => 'CS-NM', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Customer Service (South)', 'code' => 'CS-S', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Da Nang Branch', 'code' => 'DN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Delivery Control', 'code' => 'DC', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Drybody & Glazing 1', 'code' => 'DG1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Drybody & Glazing 2 (HY)', 'code' => 'DG2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Export & Inventory Control', 'code' => 'EIC', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Export-Import 1', 'code' => 'EI1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Export-Import 2 (HY)', 'code' => 'EI2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Export-Import 3 (VP)', 'code' => 'EI3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Faucet Development (VP)', 'code' => 'FD-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Final Inspection 1', 'code' => 'FI1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Final Inspection 2 (HY)', 'code' => 'FI2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Finance & Tax 1', 'code' => 'FT1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Firing 1', 'code' => 'FIR1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Firing 2 (HY)', 'code' => 'FIR2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hanoi Branch', 'code' => 'HN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ho Chi Minh Branch', 'code' => 'HCM', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HR & GA 1', 'code' => 'HRGA1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HR & GA 2 (HY)', 'code' => 'HRGA2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HR & GA 3 (VP)', 'code' => 'HRGA3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Information Technology', 'code' => 'IT', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kaizen Promotion', 'code' => 'KP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Legal', 'code' => 'LGL', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Logistic 1', 'code' => 'LOG1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Logistic 2 (HY)', 'code' => 'LOG2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Logistic 3 (VP)', 'code' => 'LOG3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Machining & Polishing (VP)', 'code' => 'MP-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Marketing', 'code' => 'MKT', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Planning', 'code' => 'PLN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Preparation & Lab 1', 'code' => 'PL1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Preparation & Lab 2 (HY)', 'code' => 'PL2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Production Control 1', 'code' => 'PC1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Production Control 2 (HY)', 'code' => 'PC2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Production Control 3 (VP)', 'code' => 'PC3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Production Engineering 1', 'code' => 'PE1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Production Engineering 2 (HY)', 'code' => 'PE2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Production Engineering & Utility 3 (VP)', 'code' => 'PEU3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Project Sales (HCM branch)', 'code' => 'PS-HCM', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Project Sales (HN branch)', 'code' => 'PS-HN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Purchasing 1', 'code' => 'PUR1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Purchasing 2 (HY)', 'code' => 'PUR2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Purchasing 3 (VP)', 'code' => 'PUR3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Quality Assurance 1', 'code' => 'QA1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Quality Assurance 2 (HY)', 'code' => 'QA2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Quality Assurance 3 (VP)', 'code' => 'QA3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Quality Inspection (VP)', 'code' => 'QI-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Quality Promotion', 'code' => 'QP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Safety & Environment 1', 'code' => 'SE1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Safety & Environment 2 (HY)', 'code' => 'SE2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Safety & Environment 3 (VP)', 'code' => 'SE3-VP', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sales Planning (HN branch)', 'code' => 'SP-HN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sales Promotion (HCM branch)', 'code' => 'SPR-HCM', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sales Promotion (HN branch)', 'code' => 'SPR-HN', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sanitary Assembling & Bathtub Production', 'code' => 'SAB', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sanitary Parts Development', 'code' => 'SPD', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sanitary ware Development 1', 'code' => 'SWD1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sanitary ware Development 2 (HY)', 'code' => 'SWD2-HY', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Training Development & Communication Center', 'code' => 'TDCC', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Utility & Engineering 1', 'code' => 'UE1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Utility & Engineering 2 (HY)', 'code' => 'UE2-HY', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
