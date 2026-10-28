<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;
use ovidiuro\myfinance2\Tests\Support\FillsRouteParameters;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * Every MyFinance2 page and AJAX endpoint runs 'web', then the app's own middleware (myfinance2.middleware), then the
 * access gate, which no config can take away: whoever the gate refuses is refused everywhere, whoever it lets in
 * opens the pages. Acts as a user with no MyFinance2 data, never saved.
 */
class RouteGateTest extends TestCase
{
    use FillsRouteParameters;

    private const ROUTES = __DIR__ . '/../../../src/routes';

    /**
     * Light pages a user with no data opens (no live quotes needed).
     */
    private const PAGES = [
        'myfinance2::accounts.index', 'myfinance2::currencies.index', 'myfinance2::cash-balances.index',
        'myfinance2::dividends.index', 'myfinance2::ledger-transactions.index', 'myfinance2::price-alerts.index',
        'myfinance2::stock-splits.index', 'myfinance2::price-alerts.history',
    ];

    public function test_every_route_runs_web_then_the_apps_middleware_then_the_access_gate(): void
    {
        $routes = $this->_packageRoutes(RouteFacade::getRoutes()->getRoutes());
        $this->assertGreaterThan(100, count($routes), 'Expected the MyFinance2 routes to be registered');
        $this->assertSame([], $this->_otherStacks($routes, ['web', 'can:myfinance2.access']));

        $configured = $this->_routesWithMiddleware(['tst.first', 'tst.second']);
        $this->assertSame([], $this->_otherStacks($configured,
            ['web', 'tst.first', 'tst.second', 'can:myfinance2.access']));
    }

    public function test_whoever_the_access_gate_refuses_is_refused_everywhere(): void
    {
        Gate::define('myfinance2.access', fn () => false);
        $this->actingAs($this->userWithoutData());

        $this->assertSame([], $this->_notRefused(), 'MyFinance2 routes a refused user was not refused on');
    }

    public function test_a_guest_is_refused_everywhere(): void
    {
        // The gates are open to every user (TestCase), not to guests: Laravel sends them to the app's login page
        $this->assertSame([], $this->_notRefused(true), 'MyFinance2 routes a guest was not refused on');
    }

    public function test_whoever_the_access_gate_lets_in_opens_the_pages(): void
    {
        $this->actingAs($this->userWithoutData());

        foreach (self::PAGES as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    /**
     * The app's login page, where Laravel sends a guest the gate refuses (every app with users has one).
     */
    protected function defineRoutes($router): void
    {
        $router->get('tst-login', fn () => 'login')->name('login');
    }

    /**
     * Every package route (with its first method) that did not answer 403, or for a guest 403 or a redirect to the
     * login page.
     *
     * @return list<string>
     */
    private function _notRefused(bool $guest = false): array
    {
        $notRefused = [];
        foreach ($this->_packageRoutes(RouteFacade::getRoutes()->getRoutes()) as $route) {
            $method   = self::firstMethod($route);
            $response = $this->call($method, self::filledUri($route));
            $status   = $response->getStatusCode();
            $location = (string) $response->headers->get('Location');
            if ($status === 403 || ($guest && $status === 302 && $location === route('login'))) {
                continue;
            }
            $notRefused[] = "{$status} {$method} {$route->uri()}" . ($location !== '' ? " -> {$location}" : '');
        }

        return $notRefused;
    }

    /**
     * @param  list<Route>   $routes
     * @param  list<string>  $expected
     * @return list<string>
     */
    private function _otherStacks(array $routes, array $expected): array
    {
        $other = [];
        foreach ($routes as $route) {
            $middleware = array_values(array_filter($route->middleware(), 'is_string'));
            if ($middleware !== $expected) {
                $other[] = $route->uri() . ': ' . implode(', ', $middleware);
            }
        }

        return $other;
    }

    /**
     * The package's routes as its route files register them with myfinance2.middleware set to $middleware.
     *
     * @param  list<string> $middleware
     * @return list<Route>
     */
    private function _routesWithMiddleware(array $middleware): array
    {
        config(['myfinance2.middleware' => $middleware]);
        $router = new Router($this->app['events'], $this->app);
        RouteFacade::swap($router);
        require self::ROUTES . '/web.php';
        require self::ROUTES . '/api.php';

        return $this->_packageRoutes($router->getRoutes()->getRoutes());
    }

    /**
     * @param  array<Route> $routes
     * @return list<Route>
     */
    private function _packageRoutes(array $routes): array
    {
        return array_values(array_filter($routes,
            fn (Route $route) => str_starts_with($route->getActionName(), 'ovidiuro\\myfinance2\\')));
    }
}
