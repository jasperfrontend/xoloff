<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validation messages are shown verbatim beside the inputs that produced them,
 * so none of them may describe the shape of the request instead.
 *
 * Laravel names an unknown field after its key. That is tolerable for a plain
 * field, where "price_ex_vat" becomes "price ex vat", but a field expanded from
 * a wildcard is reported exactly as it arrived: "specs.0.key". This sweeps
 * every form in the application so the next one added cannot quietly reintroduce
 * that.
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each case submits something invalid enough to make every rule complain.
     *
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function invalidSubmissions(): array
    {
        return [
            'customers' => ['post', 'customers.store', [
                'company_name' => '',
                'contact_person' => '',
                'email' => 'not an email',
                'billing_address' => '',
                'country' => 'XX',
            ]],
            'product categories' => ['post', 'product-categories.store', [
                'name' => '',
            ]],
            'tax classes' => ['post', 'tax-classes.store', [
                'name' => '',
                'percentage' => 'not a number',
            ]],
            'products' => ['post', 'products.store', [
                'name' => '',
                'price_ex_vat' => 'not a number',
                'tax_class_id' => 999999,
                'category_id' => 999999,
                'specs' => [['key' => '', 'value' => '']],
            ]],
            'quotes' => ['post', 'quotes.store', [
                'customer_id' => 999999,
                'discount_type' => DiscountType::Percentage->value,
                'line_items' => [
                    [
                        'name' => '',
                        'quantity' => 'not a number',
                        'unit_price_ex_vat' => -1,
                        'tax_class_id' => 999999,
                        'discount_type' => DiscountType::Percentage->value,
                    ],
                ],
            ]],
            'quote preview' => ['post', 'quotes.preview', [
                'discount_type' => DiscountType::Fixed->value,
                'rounding_override' => 'not a number',
                'line_items' => [
                    [
                        'name' => '',
                        'quantity' => 0,
                        'unit_price_ex_vat' => 'not a number',
                        'tax_class_id' => 999999,
                        'discount_type' => DiscountType::Fixed->value,
                    ],
                ],
            ]],
            'quote texts' => ['put', 'premade-texts.update', [
                'footer' => '',
            ]],
            'app settings' => ['post', 'app-settings.update', []],
            'profile' => ['patch', 'profile.update', [
                'name' => '',
                'email' => 'not an email',
            ]],
            'password' => ['put', 'user-password.update', [
                'current_password' => '',
                'password' => 'x',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidSubmissions')]
    public function test_no_message_describes_the_shape_of_the_request(string $method, string $routeName, array $payload)
    {
        // Referenced so the rules have something real to compare against, which
        // keeps the failures about wording rather than missing records.
        Customer::factory()->create();
        ProductCategory::factory()->create();
        TaxClass::factory()->create();
        Product::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->json($method, route($routeName), $payload)
            ->assertUnprocessable();

        /** @var array<string, array<int, string>> $errors */
        $errors = $response->json('errors');

        $this->assertNotEmpty($errors, "The {$routeName} payload was supposed to be rejected.");

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->assertStringNotContainsString(
                    '_',
                    $message,
                    "A message for {$field} on {$routeName} names a field by its key: {$message}",
                );

                // A word, then an index, then another word: "specs.0.key".
                // Decimals such as "at least 0.01" are not that.
                $this->assertDoesNotMatchRegularExpression(
                    '/[a-zA-Z]\.\d+\./',
                    $message,
                    "A message for {$field} on {$routeName} exposes an array index: {$message}",
                );
            }
        }
    }
}
