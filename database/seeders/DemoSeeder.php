<?php

namespace Database\Seeders;

use App\Enums\TicketAction;
use App\Enums\TicketKind;
use App\Enums\WarrantyStatus;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\ProductModel;
use App\Models\RmaTicket;
use App\Models\ServiceCenter;
use App\Models\User;
use App\Services\TicketIntake;
use App\Services\TicketWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sample tickets that walk through every workflow. Run with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    public function __construct(private TicketIntake $intake, private TicketWorkflow $workflow) {}

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $long = User::where('email', 'long.vu@sangy.vn')->firstOrFail();
        $huy = User::where('email', 'huy@sangy.vn')->firstOrFail();

        $pixelz = $this->customer('Branch of Pixelz Co., Ltd', ['address' => 'Số 303 Lê Duẩn, Kiến An, Hải Phòng'], ['Chị Thu Anh' => '0901 234 567', 'Anh Minh (Kho)' => '0904 555 666']);
        $dung = $this->customer('Phạm Quốc Dũng', ['address' => 'Lạch Tray, Ngô Quyền, Hải Phòng'], ['Anh Dũng' => '0903 123 456']);
        $hanh = $this->customer('Võ Thị Hạnh', [], ['Chị Hạnh' => '0987 654 321']);
        $cangXanh = $this->customer('Công ty CP Cảng Xanh', ['phone' => '0225 3845 566', 'address' => 'Đình Vũ, Hải An, Hải Phòng'], ['Anh Hoàng (IT)' => '0912 345 678']);

        $dell = ServiceCenter::where('name', 'Dell Technologies Việt Nam')->firstOrFail();
        $lamHieu = ServiceCenter::where('name', 'Tin học Lâm Hiếu')->firstOrFail();
        $asusCenter = ServiceCenter::where('name', 'TTBH ASUS Hải Phòng')->firstOrFail();

        // Sửa chữa có phí, đã trả khách: bảo hành vệ sinh tản nhiệt 3 tháng, cài Win 1 tháng (đã hết hạn).
        $laptopRepair = $this->open('2026-08-20', $long, TicketKind::Repair, $dung, 'ASUS', 'P500MV', 'R7NRKD012345', 'Máy nóng, tự tắt khi chạy nặng', ['accessories' => 'Sạc zin']);
        $this->step('2026-08-20', $laptopRepair, TicketAction::Inspect, $long);
        $this->quote($laptopRepair, [['Vệ sinh, thay keo tản nhiệt', 1, 350000], ['Cài lại Windows 11, driver', 1, 150000]]);
        $this->step('2026-08-21', $laptopRepair, TicketAction::SendQuote, $long);
        $this->step('2026-08-21', $laptopRepair, TicketAction::AcceptQuote, $long, ['note' => 'Khách đồng ý qua điện thoại']);
        $this->step('2026-08-26', $laptopRepair, TicketAction::FinishRepair, $long, [...$this->warranty($laptopRepair, [3, 1]), 'note' => 'Vệ sinh, thay keo tản nhiệt, cài lại Windows']);
        $laptopRepair->update(['erp_receipt_no' => 'V223-000418', 'erp_return_no' => 'V233-000392']);
        $this->step('2026-08-27', $laptopRepair, TicketAction::ReturnToCustomer, $long, ['returned_date' => '2026-08-27']);

        // Khách quay lại bảo hành sửa chữa theo phiếu trên: lỗi nóng máy thuộc hạng mục đã bảo hành.
        $claim = $this->open('2026-09-24', $long, TicketKind::Repair, $dung, 'ASUS', 'P500MV', 'R7NRKD012345', 'Máy lại nóng, quạt kêu to rồi tự tắt', [
            'accessories' => 'Sạc zin', 'claim_ticket_id' => $laptopRepair->id,
        ]);
        $this->step('2026-09-24', $claim, TicketAction::Inspect, $long);
        $this->step('2026-09-25', $claim, TicketAction::ClaimCovered, $long, [
            'claim_item_id' => $laptopRepair->warrantyItems()->first()->id, 'note' => 'Keo tản nhiệt khô, tra lại keo và vệ sinh quạt',
        ]);
        $this->step('2026-09-26', $claim, TicketAction::ReturnToCustomer, $long, ['returned_date' => '2026-09-26']);

        // Hãng bảo hành tại nhà khách: Dell đổi màn hình mới cùng model.
        $monitor = $this->open('2026-09-02', $long, TicketKind::Onsite, $pixelz, 'DELL', 'U3223QE', 'D6R49P3', 'Chân đế màn hình gãy khớp, màn không đứng được', [
            'service_center_id' => $dell->id, 'vendor_case_no' => '92460267312', 'appointment_date' => '2026-09-05',
        ]);
        $monitor->replacedParts()->create(['rma_center_shipment_id' => $monitor->pendingShipment->id, 'part_code' => '5JKJK', 'description' => 'ASSY,BASE,DIS,U3223QE,ANZ', 'quantity' => 1]);
        $this->step('2026-09-05', $monitor, TicketAction::VendorDoneAtCustomer, $long, ['done_date' => '2026-09-05', 'new_serial' => '2CRDF34', 'note' => 'Dell thay chân đế, đổi máy mới SRV. Tag 2CRDF34']);

        // Nhận về gửi TTBH: Lâm Hiếu đổi sang nguồn khác model, chờ trả khách.
        $psu = $this->open('2026-09-08', $huy, TicketKind::CarryIn, $pixelz, 'VSP', 'E550W', '0W00102IZ052500335', 'Bộ nguồn không lên, quạt không quay', ['accessories' => 'Dây nguồn']);
        $this->step('2026-09-08', $psu, TicketAction::Inspect, $huy);
        $this->step('2026-09-09', $psu, TicketAction::SendToCenter, $huy, ['service_center_id' => $lamHieu->id, 'sent_date' => '2026-09-09']);
        $this->step('2026-09-20', $psu, TicketAction::ReceiveFromCenter, $huy, [
            'back_date' => '2026-09-20', 'center_return_no' => 'LH-0912', 'new_serial' => 'P650W40102107250414',
            'new_model_id' => $this->model('VSP', 'E650W')->id, 'note' => 'Hết nguồn 550W, Lâm Hiếu đổi lên nguồn 650W',
        ], [$this->photo('NHAN VE TU TTBH - P650W40102107250414')]);

        // Sửa chữa: khách đồng ý báo giá, đang sửa.
        $tv = $this->open('2026-09-10', $long, TicketKind::Repair, $hanh, 'TCL', '55P735', 'TCL55P7A90871', 'Màn hình sọc ngang, có tiếng không có hình', ['accessories' => 'Remote']);
        $this->step('2026-09-10', $tv, TicketAction::Inspect, $long);
        $this->quote($tv, [['Panel 55 inch', 1, 2900000], ['Công thay panel', 1, 300000]]);
        $this->step('2026-09-11', $tv, TicketAction::SendQuote, $long);
        $this->step('2026-09-12', $tv, TicketAction::AcceptQuote, $long, ['note' => 'Khách đồng ý báo giá']);
        $tv->update(['erp_receipt_no' => 'V223-000455']);

        // Nhận về gửi TTBH: đang ở TTBH ASUS.
        $laptop = $this->open('2026-09-15', $long, TicketKind::CarryIn, $dung, 'ASUS', 'P500MV', 'R7NRKD012345', 'Không nhận sạc, pin không vào', ['accessories' => 'Sạc zin']);
        $this->step('2026-09-15', $laptop, TicketAction::Inspect, $long);
        $this->step('2026-09-16', $laptop, TicketAction::SendToCenter, $long, ['service_center_id' => $asusCenter->id, 'sent_date' => '2026-09-16', 'vendor_case_no' => 'ASUS-HP-778812']);

        // Sửa chữa: chờ khách duyệt báo giá.
        $printer = $this->open('2026-09-18', $huy, TicketKind::Repair, $cangXanh, 'EPSON', 'LQ310', 'X3GY104522', 'In mờ, mất nét đầu kim');
        $this->step('2026-09-18', $printer, TicketAction::Inspect, $huy);
        $this->quote($printer, [['Đầu kim LQ-310', 1, 850000], ['Công thay và căn chỉnh', 1, 150000]]);
        $this->step('2026-09-19', $printer, TicketAction::SendQuote, $huy, ['note' => 'Đã báo giá qua điện thoại']);

        // Sửa chữa: báo phế.
        $pc = $this->open('2026-09-19', $long, TicketKind::Repair, $dung, 'HP', 'ProDesk 400 G7', '8CG1234XYZ', 'Không lên hình, nghi hỏng mainboard');
        $this->step('2026-09-19', $pc, TicketAction::Inspect, $long);
        $this->step('2026-09-21', $pc, TicketAction::Scrap, $long, ['note' => 'Mainboard hỏng, hãng đã ngừng cung cấp linh kiện thay thế']);

        // Sửa chữa: lỗi đơn giản, miễn phí, bảo hành 1 tháng.
        $laser = $this->open('2026-09-22', $huy, TicketKind::Repair, $cangXanh, 'CANON', 'LBP2900', 'LBP-2231907', 'Kẹt giấy liên tục');
        $this->step('2026-09-22', $laser, TicketAction::Inspect, $huy);
        $this->step('2026-09-23', $laser, TicketAction::MarkFree, $huy, [
            'warranty_items' => [['description' => 'Vệ sinh bao lụa, hết kẹt giấy', 'months' => 1]],
            'warranty_exclusions' => $laser->device->productModel->deviceType->warranty_exclusions,
            'note' => 'Vệ sinh bao lụa, hết kẹt giấy',
        ]);

        // Hãng bảo hành tại Sang Y: máy đã mang về, chờ Dell đến.
        $this->open('2026-09-29', $huy, TicketKind::OnsiteSangy, $cangXanh, 'DELL', 'Latitude 5440', '7HKQ2V3', 'Màn hình laptop chập chờn, sọc ngang khi mở gập', [
            'accessories' => 'Sạc, túi chống sốc', 'service_center_id' => $dell->id, 'vendor_case_no' => '92471133580', 'appointment_date' => '2026-10-01',
            'note' => 'Nhận máy về Sang Y, đã mở case Dell',
        ]);

        // Sửa chữa: vừa nhận máy.
        $this->open('2026-09-29', $huy, TicketKind::Repair, $pixelz, 'DELL', 'Latitude 5440', '5440HX77Q', 'Liệt một số phím bàn phím', ['accessories' => 'Sạc']);

        Carbon::setTestNow();

        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function open(string $date, User $user, TicketKind $kind, Customer $customer, string $brand, string $model, string $serial, string $fault, array $extra = []): RmaTicket
    {
        Carbon::setTestNow(Carbon::parse($date.' 08:30'));
        $productModel = $this->model($brand, $model);

        return $this->intake->open([
            'kind' => $kind->value,
            'warranty_status' => ($kind->isWarranty() ? WarrantyStatus::InWarranty : WarrantyStatus::OutOfWarranty)->value,
            'serial_number' => $serial,
            'device_type_id' => $productModel->device_type_id,
            'brand_id' => $productModel->brand_id,
            'product_model_id' => $productModel->id,
            'customer_id' => $customer->id,
            'contact_id' => $customer->contacts()->orderBy('id')->value('id'),
            'fault_description' => $fault,
            'received_date' => $date,
            'technician_id' => $user->id,
            ...$extra,
        ], $kind->takesDeviceIn() ? [$this->photo('NHAN MAY - '.$serial), $this->photo('NHAN MAY - '.$serial.' - TEM BH')] : [], $user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $photos
     */
    private function step(string $date, RmaTicket $ticket, TicketAction $action, User $user, array $input = [], array $photos = []): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 15:00'));

        if ($action->requiresPhoto()) {
            $photos[] = $this->photo('TRA KHACH - '.$ticket->ticket_no);
        }

        $this->workflow->perform($ticket->refresh(), $action, $input, $user, $photos);
    }

    /**
     * A placeholder device photo, generated so the demo tickets carry the photos the workflow requires.
     */
    private function photo(string $caption): UploadedFile
    {
        $image = imagecreatetruecolor(800, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 231, 237, 234));
        imagefilledrectangle($image, 170, 130, 630, 430, imagecolorallocate($image, 60, 72, 68));
        imagefilledrectangle($image, 190, 150, 610, 410, imagecolorallocate($image, 120, 150, 140));
        $ink = imagecolorallocate($image, 31, 74, 61);
        imagestring($image, 5, 30, 30, 'SANG Y RMA - ANH MINH HOA', $ink);
        imagestring($image, 5, 30, 550, $caption, $ink);

        $path = tempnam(sys_get_temp_dir(), 'rma');
        imagejpeg($image, $path, 80);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, Str::slug($caption).'.jpg', 'image/jpeg', null, true);
    }

    /**
     * Warranty input for "Sửa xong": one number of months per quote line (null = not warranted), plus the type's exclusions.
     *
     * @param  list<int|null>  $months
     * @return array<string, mixed>
     */
    private function warranty(RmaTicket $ticket, array $months): array
    {
        return [
            'warranty_items' => $ticket->quoteItems()->orderBy('id')->get()->values()
                ->map(fn ($line, int $index) => ['quote_item_id' => $line->id, 'description' => $line->description, 'months' => $months[$index] ?? null])
                ->all(),
            'warranty_exclusions' => $ticket->device->productModel->deviceType->warranty_exclusions,
        ];
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $lines
     */
    private function quote(RmaTicket $ticket, array $lines): void
    {
        foreach ($lines as [$description, $quantity, $price]) {
            $ticket->quoteItems()->create(['description' => $description, 'quantity' => $quantity, 'unit_price' => $price]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $contacts  name => phone
     */
    private function customer(string $name, array $attributes, array $contacts): Customer
    {
        $customer = Customer::firstOrCreate(['name' => $name], $attributes);

        foreach ($contacts as $contactName => $phone) {
            $customer->contacts()->firstOrCreate(['phone' => $phone], ['name' => $contactName]);
        }

        return $customer;
    }

    private function model(string $brand, string $code): ProductModel
    {
        return ProductModel::where('brand_id', Brand::where('name', $brand)->value('id'))->where('code', $code)->firstOrFail();
    }
}
