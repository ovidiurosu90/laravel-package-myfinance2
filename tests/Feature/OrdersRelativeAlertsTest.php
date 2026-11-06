<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\Tests\TestCase;
use ovidiuro\myfinance2\Tests\Support\TestUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Orders and RELATIVE price alerts: a relative alert whose moving target happens to equal an
 * order's limit price is never treated as the order's companion alert (never shown as "Linked to
 * this order", never rewritten or deleted by an order edit or fill). Synthetic TST.* rows only,
 * rolled back on both connections. The companion alert an open order still keeps in sync names the
 * order on its edit form, and an edit that breaks the link needs a confirmation.
 */
class OrdersRelativeAlertsTest extends TestCase
{
    private const SYMBOL = 'TST.ORDREL';

    private ?TestUser $_user = null;

    private ?Account $_account = null;

    protected function setUp(): void
    {
        parent::setUp();

        AssignedToUserScope::enable();
        $this->_user = TestUser::query()->orderBy('id')->first();

        if (!$this->_user) {
            $this->markTestSkipped('Requires at least 1 user in database');
        }

        $this->actingAs($this->_user);
        $this->_account = Account::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $this->_user->id)
            ->first();
    }

    protected function tearDown(): void
    {
        AssignedToUserScope::enable();
        Auth::forgetGuards();
        parent::tearDown();
    }

    private function _placedBuyOrder(): Order
    {
        if (!$this->_account) {
            $this->markTestSkipped('Requires at least 1 account for the test user');
        }

        return Order::create([
            'symbol'      => self::SYMBOL,
            'action'      => 'BUY',
            'status'      => 'PLACED',
            'account_id'  => $this->_account->id,
            'quantity'    => '10.00000000',
            'limit_price' => '100.0000',
            'placed_at'   => now(),
        ]);
    }

    /**
     * An ACTIVE alert at the order's limit price (100), PRICE_BELOW like a BUY companion alert.
     */
    private function _alertAtLimitPrice(array $fields): PriceAlert
    {
        $id = DB::connection(config('myfinance2.db_connection'))->table('price_alerts')->insertGetId(array_merge([
            'user_id'               => $this->_user->id,
            'symbol'                => self::SYMBOL,
            'alert_type'            => 'PRICE_BELOW',
            'target_price'          => 100.0,
            'target_mode'           => 'RELATIVE',
            'reference_type'        => 'HIGH',
            'reference_window'      => '2y',
            'offset_pct'            => 20,
            'reference_price'       => 125.0,
            'reference_resolved_at' => now(),
            'status'                => 'ACTIVE',
            'source'                => 'manual',
            'notification_channel'  => 'email',
            'trigger_count'         => 0,
            'expires_at'            => now()->addDays(5),
            'created_at'            => now(),
            'updated_at'            => now(),
        ], $fields));

        return PriceAlert::findOrFail($id);
    }

    public function test_relative_alert_at_the_limit_price_is_not_linked_to_the_order(): void
    {
        $this->_placedBuyOrder();
        $relative = $this->_alertAtLimitPrice([]);

        $html = $this->withoutMiddleware()->actingAs($this->_user)
            ->get(route('myfinance2::orders.index'))
            ->assertOk()
            ->getContent();

        $editUrl = preg_quote(route('myfinance2::price-alerts.edit', $relative->id), '/');
        $this->assertMatchesRegularExpression('/<a href="' . $editUrl . '"[^>]*title="([^"]*)"/s', $html);
        preg_match('/<a href="' . $editUrl . '"[^>]*title="([^"]*)"/s', $html, $matches);

        $this->assertStringNotContainsString('Linked to this order', $matches[1]);
        $this->assertStringContainsString('20% below 2Y high', html_entity_decode($matches[1]));
    }

    public function test_filling_the_order_keeps_a_relative_alert_and_deletes_the_fixed_companion(): void
    {
        $order = $this->_placedBuyOrder();
        // Even with source = order (only possible through a direct write), a RELATIVE alert is
        // never treated as the companion alert.
        $relative  = $this->_alertAtLimitPrice(['source' => 'order']);
        $companion = $this->_alertAtLimitPrice([
            'source' => 'order', 'target_mode' => 'FIXED', 'reference_type' => null,
            'reference_window' => null, 'offset_pct' => null, 'reference_price' => null,
            'reference_resolved_at' => null,
        ]);

        $this->withoutMiddleware()->actingAs($this->_user)
            ->post(route('myfinance2::orders.fill', $order->id))
            ->assertSessionHas('success');

        $this->assertNotNull(PriceAlert::find($relative->id));
        $this->assertNull(PriceAlert::find($companion->id));
    }

    public function test_open_alerts_banner_shows_the_relative_label(): void
    {
        $this->_alertAtLimitPrice([]);

        $this->withoutMiddleware()->actingAs($this->_user)
            ->get(route('myfinance2::orders.open-alerts-for-symbol', ['symbol' => self::SYMBOL]))
            ->assertOk()
            ->assertSee('(20% below 2Y high)');
    }

    /**
     * The FIXED companion alert an order creates: source = order, ACTIVE, expiring, at the limit price.
     */
    private function _companionAlert(array $fields = []): PriceAlert
    {
        return $this->_alertAtLimitPrice(array_merge([
            'source' => 'order', 'target_mode' => 'FIXED', 'reference_type' => null,
            'reference_window' => null, 'offset_pct' => null, 'reference_price' => null,
            'reference_resolved_at' => null,
        ], $fields));
    }

    private function _editForm(PriceAlert $alert): string
    {
        // Full middleware stack: the form reads $errors, which ShareErrorsFromSession provides
        return $this->actingAs($this->_user)
            ->get(route('myfinance2::price-alerts.edit', $alert->id))
            ->assertOk()
            ->getContent();
    }

    private function _update(PriceAlert $alert, array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware()->actingAs($this->_user)
            ->from(route('myfinance2::price-alerts.edit', $alert->id))
            ->put(route('myfinance2::price-alerts.update', $alert->id), array_merge([
                'symbol'       => self::SYMBOL,
                'alert_type'   => 'PRICE_BELOW',
                'target_mode'  => 'FIXED',
                'target_price' => '100',
                'status'       => 'ACTIVE',
                'expires_at'   => $alert->expires_at?->format('Y-m-d H:i:s'),
            ], $fields));
    }

    public function test_companion_alert_edit_form_names_the_order_and_warns_before_unlinking(): void
    {
        $order     = $this->_placedBuyOrder();
        $companion = $this->_companionAlert();

        $this->assertSame($order->id, $companion->linkedOrder()?->id);
        $html = $this->_editForm($companion);

        // The title and the confirmation link to the order, in a new tab (the form's edits are kept).
        $orderLink = '<a href="' . route('myfinance2::orders.edit', $order->id) . '" target="_blank" rel="noopener"';
        $this->assertStringContainsString($orderLink, $html);
        $this->assertStringContainsString('Linked to order #' . $order->id, $html);
        $this->assertMatchesRegularExpression(
            '/id="linked-order-confirm-title">.*Break the link to ' . preg_quote($orderLink, '/') . '[^>]*>order #'
                . $order->id . '<\/a>\?/s',
            $html
        );
        $this->assertStringContainsString('id="linked-order-warning"', $html);
        $this->assertStringContainsString('id="linked-order-reset"', $html);
        $this->assertStringContainsString('id="linked-order-dismiss"', $html);
        $this->assertStringContainsString('id="linked-order-confirm-modal"', $html);
        // Every field stays editable: the target mode is not locked.
        $this->assertDoesNotMatchRegularExpression('/id="target_mode-(relative|fixed)"[^>]*disabled/s', $html);
    }

    /**
     * The alerts list shows the order a linked alert follows next to its "order" source, linked in a
     * new tab; an "order" alert no open order follows shows the source only.
     */
    public function test_alerts_list_links_the_order_of_a_linked_alert(): void
    {
        $order     = $this->_placedBuyOrder();
        $companion = $this->_companionAlert();
        $paused    = $this->_companionAlert(['status' => 'PAUSED']);

        $this->assertSame([$companion->id], array_keys(PriceAlert::linkedOrders([$companion, $paused])));

        $html = $this->actingAs($this->_user)
            ->get(route('myfinance2::price-alerts.index', ['view' => 'all']))
            ->assertOk()
            ->getContent();

        $orderLink = '<a href="' . route('myfinance2::orders.edit', $order->id) . '"';
        $this->assertSame(1, substr_count($html, $orderLink));
        $this->assertMatchesRegularExpression(
            '/' . preg_quote($orderLink, '/') . '\s+target="_blank" rel="noopener"[^>]*>#' . $order->id . '<\/a>/',
            $html
        );
    }

    public function test_unlinking_edit_needs_a_confirmation(): void
    {
        $this->_placedBuyOrder();
        $companion = $this->_companionAlert();

        $this->_update($companion, ['target_price' => '95'])
            ->assertRedirect(route('myfinance2::price-alerts.edit', $companion->id))
            ->assertSessionHas('unlink_confirm', true)
            ->assertSessionHasInput('target_price', '95');
        $this->assertEqualsWithDelta(100.0, (float) $companion->fresh()->target_price, 0.000001);

        $this->_update($companion, ['target_price' => '95', 'confirm_unlink' => '1'])
            ->assertRedirect(route('myfinance2::price-alerts.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'no longer follows order'));
        $this->assertEqualsWithDelta(95.0, (float) $companion->fresh()->target_price, 0.000001);
        $this->assertNull($companion->fresh()->linkedOrder());
    }

    public function test_pausing_or_switching_to_relative_also_needs_a_confirmation(): void
    {
        $this->_placedBuyOrder();
        $companion = $this->_companionAlert();

        $this->_update($companion, ['status' => 'PAUSED'])->assertSessionHas('unlink_confirm', true);
        $this->_update($companion, ['target_mode' => 'RELATIVE', 'reference_key' => 'HIGH:1y', 'offset_pct' => '5'])
            ->assertSessionHas('unlink_confirm', true);
        $this->_update($companion, ['expires_at' => ''])->assertSessionHas('unlink_confirm', true);

        $this->assertSame('ACTIVE', $companion->fresh()->status);
        $this->assertSame('FIXED', $companion->fresh()->target_mode);
    }

    public function test_edit_keeping_the_link_saves_without_a_confirmation(): void
    {
        $order     = $this->_placedBuyOrder();
        $companion = $this->_companionAlert();

        $this->_update($companion, ['notes' => 'still linked'])
            ->assertRedirect(route('myfinance2::price-alerts.index'))
            ->assertSessionMissing('unlink_confirm')
            ->assertSessionHas('success', fn ($message) => !str_contains($message, 'no longer follows'));

        $this->assertSame('still linked', $companion->fresh()->notes);
        $this->assertSame($order->id, $companion->fresh()->linkedOrder()?->id);
    }

    /**
     * An alert with source = order that no open order keeps in sync (created after expiring an
     * order, the order gone, paused, or re-priced) is not linked: no order in the title, no warning.
     */
    public function test_order_alert_no_open_order_follows_is_not_linked(): void
    {
        $withoutOrder = $this->_companionAlert(['expires_at' => null]);
        $this->assertNull($withoutOrder->linkedOrder());

        $this->_placedBuyOrder();
        $paused    = $this->_companionAlert(['status' => 'PAUSED']);
        $repriced  = $this->_companionAlert(['target_price' => 95.0]);
        $noExpiry  = $this->_companionAlert(['expires_at' => null]);

        foreach ([$paused, $repriced, $noExpiry] as $alert) {
            $this->assertNull($alert->linkedOrder(), 'alert ' . $alert->id);
        }

        $html = $this->_editForm($paused);
        $this->assertStringNotContainsString('Linked to order', $html);
        $this->assertStringNotContainsString('id="linked-order-warning"', $html);

        $this->_update($paused, ['status' => 'PAUSED', 'target_price' => '90'])
            ->assertSessionMissing('unlink_confirm')
            ->assertRedirect(route('myfinance2::price-alerts.index'));
    }
}
