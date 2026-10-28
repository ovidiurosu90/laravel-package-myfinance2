<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature\Security;

use Illuminate\Support\Facades\Gate;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * MyFinance2 asks the app who may use it through two gates (myfinance2.access, myfinance2.manage) and denies everyone,
 * with the reason, until the app defines them; the Finance menu follows the access gate. Reads only.
 */
class DefaultGatesTest extends TestCase
{
    protected bool $opensTheGates = false;

    public function test_without_the_apps_gates_everyone_is_blocked_with_the_reason(): void
    {
        $user = $this->userWithoutData();

        $response = Gate::forUser($user)->inspect('myfinance2.manage', 'trades');
        $this->assertTrue($response->denied());
        $this->assertStringContainsString("the app has to define the 'myfinance2.manage' gate", $response->message());

        $this->actingAs($user)->get(route('myfinance2::home'))
            ->assertForbidden()
            // Escaped like the 403 page prints it ({{ }} turns the quotes into &#039;)
            ->assertSee("MyFinance2 access is not configured: the app has to define the 'myfinance2.access' gate");
        $this->assertStringNotContainsString('navbar-dropdown-myfinance2', $this->_menu());
    }

    public function test_the_menu_shows_for_the_users_the_access_gate_lets_in(): void
    {
        Gate::define('myfinance2.access', fn () => true);
        $this->actingAs($this->userWithoutData());

        $this->assertStringContainsString('navbar-dropdown-myfinance2', $this->_menu());
    }

    private function _menu(): string
    {
        return view('myfinance2::partials.nav')->render();
    }
}
