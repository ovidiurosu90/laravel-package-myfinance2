<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Finder\Finder;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * Every MyFinance2 route has a name (myfinance2::...) and its pages build links from those names, so the routes can
 * move under myfinance2.route_prefix (an app may keep them at the root) without a broken link. Reads the route files
 * and the package's sources only.
 */
class RoutePrefixTest extends TestCase
{
    private const SOURCES = __DIR__ . '/../../src';

    public function test_every_route_of_the_package_has_a_name(): void
    {
        $unnamed = [];
        foreach ($this->_packageRoutes(RouteFacade::getRoutes()->getRoutes()) as $route) {
            if (!str_starts_with((string) $route->getName(), 'myfinance2::')) {
                $unnamed[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $unnamed, "MyFinance2 routes without a myfinance2:: name:\n" . implode("\n", $unnamed));
    }

    public function test_a_prefix_moves_every_route_and_keeps_its_name(): void
    {
        $atRoot   = $this->_names(RouteFacade::getRoutes()->getRoutes());
        $prefixed = $this->_routesUnder('finance');

        $this->assertNotEmpty($prefixed);
        foreach ($prefixed as $route) {
            $this->assertStringStartsWith('finance/', $route->uri(), $route->getName());
        }
        $this->assertEqualsCanonicalizing($atRoot, $this->_names($prefixed));
    }

    public function test_the_package_builds_its_links_from_route_names(): void
    {
        $offending = [];
        $finder    = (new Finder())->files()->name('*.php')->in(self::SOURCES)->notPath('routes');
        foreach ($finder as $file) {
            // url('/') (the app's home page) is fine; a path of the package's own is not
            if (preg_match("#url\(\s*['\"]/?[a-z]|Request::is\(|request\(\)->is\(#", $file->getContents())) {
                $offending[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offending, "Links built from a path instead of a route name:\n"
            . implode("\n", $offending));
    }

    /**
     * The package's routes as its route files register them with myfinance2.route_prefix set to $prefix.
     *
     * @return list<Route>
     */
    private function _routesUnder(string $prefix): array
    {
        config(['myfinance2.route_prefix' => $prefix]);
        $router = new Router($this->app['events'], $this->app);
        RouteFacade::swap($router);
        foreach (['web', 'api'] as $file) {
            require self::SOURCES . "/routes/{$file}.php";
        }

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

    /**
     * @param  array<Route> $routes
     * @return list<string>
     */
    private function _names(array $routes): array
    {
        return array_map(fn (Route $route) => (string) $route->getName(), $this->_packageRoutes($routes));
    }
}
