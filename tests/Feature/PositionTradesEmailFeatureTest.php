<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\Tests\TestCase;
use ovidiuro\myfinance2\Tests\Support\TestUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\AlertService;
use ovidiuro\myfinance2\App\Services\PositionTimeline;
use ovidiuro\myfinance2\App\Services\SymbolPositionReturns;
use ovidiuro\myfinance2\Mail\PriceAlertTriggered;

/**
 * The trades table the alert emails show under a position (emails/partials/position-trades): the price alert's
 * Position card and the peak-proximity alert's Open positions card list the same rows (trades and splits, newest
 * first), the price alert names each trade's account when the position spans several, and the alert engine hands
 * the email the position's trades.
 *
 * The trades, splits and accounts are unsaved models; the only row written is the synthetic alert (TRADES.TST),
 * rolled back.
 */
class PositionTradesEmailFeatureTest extends TestCase
{
    public const HELD   = 'HELD.TST';
    private const SYMBOL = 'TRADES.TST';

    private ?TestUser $_user = null;

    protected function setUp(): void
    {
        parent::setUp();

        AssignedToUserScope::enable();
        $this->_user = TestUser::query()->orderBy('id')->first();
        if (!$this->_user) {
            $this->markTestSkipped('Requires at least 1 user in database');
        }
        $this->actingAs($this->_user);
    }

    protected function tearDown(): void
    {
        Auth::forgetGuards();
        parent::tearDown();
    }

    private function _alert(): PriceAlert
    {
        return PriceAlert::create([
            'symbol'               => self::SYMBOL,
            'alert_type'           => 'PRICE_ABOVE',
            'target_price'         => '112.000000',
            'status'               => 'ACTIVE',
            'source'               => 'manual',
            'notification_channel' => 'email',
            'trigger_count'        => 0,
            'trade_currency_id'    => Currency::where('iso_code', 'USD')->value('id'),
        ]);
    }

    /**
     * An unsaved trade in USD, in the named account.
     */
    private static function _trade(string $date, string $action, float $qty, float $price, int $accountId): Trade
    {
        $trade = (new Trade())->forceFill([
            'symbol' => self::SYMBOL, 'timestamp' => Carbon::parse($date), 'action' => $action,
            'quantity' => $qty, 'unit_price' => $price, 'account_id' => $accountId,
        ]);
        $trade->setRelation('tradeCurrencyModel', (new Currency())->forceFill(['display_code' => '$']));
        $trade->setRelation('accountModel', (new Account())->forceFill(['name' => "Broker {$accountId}"]));

        return $trade;
    }

    private static function _split(string $date): StockSplit
    {
        return (new StockSplit())->forceFill([
            'symbol' => self::SYMBOL, 'split_date' => Carbon::parse($date),
            'ratio_numerator' => 2, 'ratio_denominator' => 1,
        ]);
    }

    private static function _position(array $timeline): array
    {
        return [
            'today_gain_eur' => 98.83, 'today_gain_pct' => 1.07, 'gain_eur' => -4256.0, 'gain_pct' => -31.37,
            'quantity' => 93.0, 'avg_unit_cost' => 163.34, 'timeline' => $timeline,
        ];
    }

    /**
     * Covers: the price alert's Position card lists the trades and splits newest first, without an Account column
     * for a single account.
     */
    public function test_price_alert_lists_the_position_trades_newest_first(): void
    {
        $timeline = PositionTimeline::merge(
            [self::_trade('2024-03-04', 'BUY', 40, 180.5, 1), self::_trade('2025-02-10', 'BUY', 53, 150.0, 1)],
            [self::_split('2024-09-01')]
        );

        $html = (new PriceAlertTriggered($this->_alert(), 112.10, self::_position($timeline)))->render();

        $this->assertStringContainsString('<th>Unit price</th>', $html);
        $this->assertStringNotContainsString('<th>Account</th>', $html);
        $newest = strpos($html, '2025-02-10');
        $split  = strpos($html, '2:1 split');
        $oldest = strpos($html, '2024-03-04');
        $this->assertNotFalse($newest);
        $this->assertTrue($newest < $split && $split < $oldest, 'trades and splits are listed newest first');
        $this->assertStringContainsString('53x', $html);
        $this->assertMatchesRegularExpression('/150\.00.{0,8}\$/u', $html);
    }

    /**
     * Covers: a position held in several accounts names each trade's account; a symbol not held has no trades table.
     */
    public function test_price_alert_names_the_accounts_and_skips_the_table_when_not_held(): void
    {
        $timeline = PositionTimeline::merge(
            [self::_trade('2024-03-04', 'BUY', 40, 180.5, 1), self::_trade('2025-02-10', 'BUY', 53, 150.0, 2)],
            []
        );
        $alert = $this->_alert();

        $multi = (new PriceAlertTriggered($alert, 112.10, self::_position($timeline)))->render();
        $this->assertStringContainsString('<th>Account</th>', $multi);
        $this->assertStringContainsString('Broker 1', $multi);
        $this->assertStringContainsString('Broker 2', $multi);

        $notHeld = (new PriceAlertTriggered($alert, 112.10, ['today_gain_pct' => 1.07]))->render();
        $this->assertStringNotContainsString('<th>Unit price</th>', $notHeld);
        $this->assertStringContainsString('No open position found for ' . self::SYMBOL . '.', $notHeld);
    }

    /**
     * Covers: the peak-proximity alert's Open positions card renders the same shared trades table.
     */
    public function test_peak_open_positions_card_uses_the_shared_trades_table(): void
    {
        $account = (new Account())->forceFill(['name' => 'Broker 1']);
        $account->setRelation('currency', (new Currency())->forceFill(['display_code' => '€']));
        $timeline = PositionTimeline::merge([self::_trade('2025-02-10', 'SELL', 7, 150.0, 1)], []);

        $html = view('myfinance2::emails.partials.peak-open-positions-card', [
            'decode'        => fn ($s) => html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'openPositions' => [[
                'accountModel' => $account, 'quantity' => 7.0, 'timeline' => $timeline,
                'market_value_in_account_currency_formatted' => '', 'cost2_in_account_currency_formatted' => '',
                'cost_in_account_currency_formatted' => '', 'average_unit_cost2_in_trade_currency_formatted' => '',
                'average_unit_cost_in_trade_currency_formatted' => '',
                'overall_change2_in_account_currency_formatted' => '',
                'overall_change_in_account_currency_formatted' => '',
            ]],
        ])->render();

        $this->assertStringContainsString('<h2>Open positions</h2>', $html);
        $this->assertStringContainsString('<th>Unit price</th>', $html);
        $this->assertStringContainsString('2025-02-10', $html);
        $this->assertStringContainsString('<td>sell</td>', $html);
        $this->assertStringNotContainsString('<th>Account</th>', $html);
    }

    /**
     * Covers: AlertService::emailPosition() turns the position's trades into the email's timeline (an unused user,
     * so no real split is read), and a symbol not held has none.
     */
    public function test_email_position_hands_the_trades_to_the_email(): void
    {
        $trades          = [self::_trade('2024-03-04', 'BUY', 40, 180.5, 1)];
        $positionReturns = new class($trades) extends SymbolPositionReturns
        {
            public function __construct(private array $_trades)
            {
            }

            public function forUser(int $userId): array
            {
                return [PositionTradesEmailFeatureTest::HELD => [
                    'gain_eur' => 1.0, 'raw_pct' => 1.0, 'quantity' => 40.0, 'avg_unit_cost2' => 180.5,
                    'trades' => $this->_trades,
                ]];
            }
        };
        $engine = new AlertService(null, $positionReturns);
        $userId = (int) StockSplit::withoutGlobalScope(AssignedToUserScope::class)->max('user_id') + 1000;

        $held = $engine->emailPosition($userId, self::HELD, [], null);
        $this->assertSame(['trade'], array_column($held['timeline'], 'type'));
        $this->assertSame($trades[0], $held['timeline'][0]['data']);

        $this->assertSame([], $engine->emailPosition($userId, 'FREE.TST', [], null)['timeline']);
    }
}
