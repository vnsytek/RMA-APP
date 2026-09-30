<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The address book of a customer. Contacts are never deleted, only switched off, so old tickets keep them.
 */
class CustomerContactController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $customer->contacts()->create($this->validated($request, $customer, null, 'contact'));

        return redirect()->to(route('customers.show', $customer).'#nguoi-lien-he')->with('status', 'Đã thêm người liên hệ.');
    }

    public function update(Request $request, Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $contact->update($this->validated($request, $customer, $contact, 'contact'.$contact->id));

        return redirect()->to(route('customers.show', $customer).'#nguoi-lien-he')->with('status', "Đã lưu người liên hệ {$contact->name}.");
    }

    public function toggle(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $contact->update(['is_active' => ! $contact->is_active]);

        return redirect()->to(route('customers.show', $customer).'#nguoi-lien-he')
            ->with('status', ($contact->is_active ? 'Đã dùng lại ' : 'Đã ngừng dùng ').$contact->name.'.');
    }

    /**
     * @return array{name: string, phone: string}
     */
    private function validated(Request $request, Customer $customer, ?CustomerContact $contact, string $bag): array
    {
        $request->merge(['name' => Str::squish((string) $request->input('name')), 'phone' => Str::squish((string) $request->input('phone'))]);

        return $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('customer_contacts', 'phone')->where('customer_id', $customer->id)->ignore($contact)],
        ], [
            'phone.unique' => 'Khách này đã có người liên hệ dùng số điện thoại này.',
        ], ['name' => 'tên người liên hệ', 'phone' => 'số điện thoại']);
    }
}
