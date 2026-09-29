<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\DeviceType;
use App\Models\ProductModel;
use App\Services\CatalogResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('catalog.index', [
            'deviceTypes' => DeviceType::withCount('productModels')->orderBy('name')->get(),
            'brands' => Brand::withCount('productModels')->orderBy('name')->get(),
            'productModels' => ProductModel::with('brand', 'deviceType')->withCount('devices')->orderBy('code')->get(),
        ]);
    }

    public function storeDeviceType(Request $request, CatalogResolver $catalog): RedirectResponse
    {
        $data = $request->validateWithBag('deviceType', [
            'name' => ['required', 'string', 'max:100'],
        ], attributes: ['name' => 'tên loại']);

        if ($existing = $catalog->findDeviceTypeByName($data['name'])) {
            return back()->withErrors(['name' => "Đã có loại \"{$existing->name}\"."], 'deviceType');
        }

        DeviceType::create(['code' => $catalog->uniqueTypeCode($data['name']), 'name' => trim($data['name'])]);

        return back()->with('status', 'Đã thêm loại thiết bị.');
    }

    public function storeBrand(Request $request, CatalogResolver $catalog): RedirectResponse
    {
        $request->merge(['name' => $catalog->brandName((string) $request->input('name'))]);

        $data = $request->validateWithBag('brand', [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')],
        ], attributes: ['name' => 'tên hãng']);

        Brand::create($data);

        return back()->with('status', 'Đã thêm hãng.');
    }

    public function storeProductModel(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('productModel', [
            'device_type_id' => ['required', 'integer', Rule::exists('device_types', 'id')],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'code' => ['required', 'string', 'max:100', Rule::unique('product_models', 'code')->where('brand_id', $request->integer('brand_id'))],
            'name' => ['nullable', 'string', 'max:255'],
        ], attributes: ['device_type_id' => 'loại thiết bị', 'brand_id' => 'hãng', 'code' => 'mã model', 'name' => 'tên model']);

        ProductModel::create($data);

        return back()->with('status', 'Đã thêm model.');
    }

    /**
     * The default "không bảo hành" list offered when a repair of this type is finished.
     */
    public function updateDeviceType(Request $request, DeviceType $deviceType): RedirectResponse
    {
        $data = $request->validateWithBag('deviceType', [
            'warranty_exclusions' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['warranty_exclusions' => 'danh sách không bảo hành']);

        $deviceType->update(['warranty_exclusions' => implode("\n", text_lines($data['warranty_exclusions'] ?? null)) ?: null]);

        return back()->with('status', "Đã lưu danh sách không bảo hành của loại \"{$deviceType->name}\".");
    }

    public function toggleDeviceType(DeviceType $deviceType): RedirectResponse
    {
        $deviceType->update(['is_active' => ! $deviceType->is_active]);

        return back();
    }

    public function toggleBrand(Brand $brand): RedirectResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return back();
    }

    public function toggleProductModel(ProductModel $productModel): RedirectResponse
    {
        $productModel->update(['is_active' => ! $productModel->is_active]);

        return back();
    }
}
