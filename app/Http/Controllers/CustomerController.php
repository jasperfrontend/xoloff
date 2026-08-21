<?php

namespace App\Http\Controllers;

use App\Enums\Salutation;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('customers/Index', [
            'customers' => Customer::query()
                ->orderBy('company_name')
                ->get(['id', 'company_name', 'first_name', 'last_name', 'email', 'country'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'company_name' => $customer->company_name,
                    // Derived from the parts rather than stored, so a list and
                    // a form can never disagree about someone's name.
                    'contact_person' => $customer->contact_person,
                    'email' => $customer->email,
                    'country' => $customer->country,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/Create', [
            ...$this->formOptions(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return to_route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/Edit', [
            'customer' => $customer,
            ...$this->formOptions(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return to_route('customers.index');
    }

    /**
     * The choices the form offers. Labelled by the server, so the wording
     * cannot drift between here and anywhere else these appear.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'countries' => Config::array('xoloff.countries'),
            'salutations' => collect(Salutation::cases())
                ->mapWithKeys(fn (Salutation $salutation): array => [
                    $salutation->value => $salutation->label(),
                ])
                ->all(),
        ];
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        // The foreign key restricts this, because quotes are financial records
        // and neither orphaning nor cascading them is acceptable. Explain that
        // rather than letting the database exception surface as a 500.
        if ($customer->quotes()->exists()) {
            return back()->withErrors([
                'customer' => __('This customer still has one or more quotes.'),
            ]);
        }

        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deleted.')]);

        return to_route('customers.index');
    }
}
