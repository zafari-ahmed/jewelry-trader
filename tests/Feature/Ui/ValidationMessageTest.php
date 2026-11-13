<?php

namespace Tests\Feature\Ui;

use App\Livewire\Customers\CustomerList;
use App\Livewire\Settings\Pos;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire validates nested state, so without a name for each path Laravel
 * puts the path itself in the message and the form tells people "The form.name
 * field is required" — the name of a variable in our code, not of the box in
 * front of them.
 */
class ValidationMessageTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        return $user->fresh();
    }

    public function test_a_blank_customer_name_is_reported_without_the_property_path(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(CustomerList::class)
            ->set('form.name', '')
            ->call('save');

        $message = $component->errors()->first('form.name');

        $this->assertStringContainsString('name field is required', $message);
        $this->assertStringNotContainsString('form.', $message);
    }

    public function test_a_settings_page_names_its_field_too(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(Pos::class)
            ->set('state.restock_fee_percent', '')
            ->call('save');

        $message = $component->errors()->first('state.restock_fee_percent');

        $this->assertStringContainsString('restock fee percent', $message);
        $this->assertStringNotContainsString('state.', $message);
    }

    public function test_an_invalid_email_names_the_email(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(CustomerList::class)
            ->set('form.name', 'Eleanor Whitcombe')
            ->set('form.email', 'not-an-address')
            ->call('save');

        $this->assertStringNotContainsString('form.', $component->errors()->first('form.email'));
    }

    public function test_a_duplicate_email_is_refused(): void
    {
        Customer::create(['name' => 'Already here', 'email' => 'eleanor@example.com']);

        Livewire::actingAs($this->superAdmin())->test(CustomerList::class)
            ->set('form.name', 'Eleanor Whitcombe')
            ->set('form.email', 'eleanor@example.com')
            ->call('save')
            ->assertHasErrors('form.email');

        $this->assertSame(1, Customer::count());
    }

    /**
     * Editing a record must not collide with itself — the classic unique-rule
     * bug, where saving a customer without changing their address is refused
     * because their own row already holds it.
     */
    public function test_saving_an_unchanged_email_on_an_existing_customer_is_allowed(): void
    {
        $customer = Customer::create(['name' => 'Eleanor Whitcombe', 'email' => 'eleanor@example.com']);

        Livewire::actingAs($this->superAdmin())->test(CustomerList::class)
            ->call('edit', $customer->id)
            ->set('form.phone', '+1 212 555 0118')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+1 212 555 0118', $customer->fresh()->phone);
    }

    /** No message anywhere in the component should carry a raw path. */
    public function test_no_message_on_the_customer_form_leaks_a_path(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(CustomerList::class)
            ->set('form.name', '')
            ->set('form.email', 'not-an-address')
            ->call('save');

        foreach ($component->errors()->all() as $message) {
            foreach (['form.', 'state.', '.*'] as $leak) {
                $this->assertStringNotContainsString($leak, $message, "Message leaks a property path: {$message}");
            }
        }
    }
}
