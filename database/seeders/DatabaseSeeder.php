<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\DeviceType;
use App\Models\ProductModel;
use App\Models\ServiceCenter;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the accounts and master data every installation needs.
     */
    public function run(): void
    {
        $password = env('RMA_SEED_PASSWORD', 'SangY@2026');

        User::firstOrCreate(['email' => 'long.vu@sangy.vn'], ['name' => 'Vũ Bảo Long', 'password' => $password, 'role' => UserRole::Admin, 'is_active' => true]);
        User::firstOrCreate(['email' => 'tuan.pham@sangy.vn'], ['name' => 'Phạm Minh Tuấn', 'password' => $password, 'role' => UserRole::User, 'is_active' => true]);

        $types = collect([
            'MONITOR' => 'Màn hình', 'LAPTOP' => 'Máy tính xách tay', 'PC' => 'Máy tính để bàn',
            'PRINTER' => 'Máy in', 'PSU' => 'Bộ nguồn', 'TV' => 'Tivi',
        ])->map(fn (string $name, string $code) => DeviceType::firstOrCreate(['code' => $code], ['name' => $name, 'warranty_exclusions' => config('rma.warranty_exclusions.'.$code)]));

        $brands = collect(['DELL', 'VSP', 'ASUS', 'EPSON', 'TCL', 'CANON', 'HP'])
            ->mapWithKeys(fn (string $name) => [$name => Brand::firstOrCreate(['name' => $name])]);

        foreach ([
            ['DELL', 'MONITOR', 'U3223QE', 'UltraSharp 32 4K'],
            ['DELL', 'MONITOR', 'U3224KB', 'UltraSharp 32 6K'],
            ['VSP', 'PSU', 'E550W', 'Nguồn VSP 550W'],
            ['VSP', 'PSU', 'E650W', 'Nguồn VSP 650W'],
            ['ASUS', 'LAPTOP', 'P500MV', 'ExpertBook P5'],
            ['EPSON', 'PRINTER', 'LQ310', 'Máy in kim LQ-310'],
            ['TCL', 'TV', '55P735', 'Google TV 55 inch'],
            ['CANON', 'PRINTER', 'LBP2900', 'Máy in laser'],
            ['HP', 'PC', 'ProDesk 400 G7', null],
            ['DELL', 'LAPTOP', 'Latitude 5440', null],
        ] as [$brand, $type, $code, $name]) {
            ProductModel::firstOrCreate(
                ['brand_id' => $brands[$brand]->id, 'code' => $code],
                ['device_type_id' => $types[$type]->id, 'name' => $name],
            );
        }

        ServiceCenter::firstOrCreate(['name' => 'Dell Technologies Việt Nam'], ['brands' => 'DELL', 'address' => 'Hãng cử kỹ thuật đến tận nơi']);
        ServiceCenter::firstOrCreate(['name' => 'Tin học Lâm Hiếu'], ['brands' => 'VSP, nguồn, linh kiện', 'phone' => '0313.513689', 'address' => 'Số 539 Thiên Lôi, Vĩnh Niệm, Lê Chân, Hải Phòng']);
        ServiceCenter::firstOrCreate(['name' => 'TTBH ASUS Hải Phòng'], ['brands' => 'ASUS', 'address' => 'Hải Phòng']);
    }
}
