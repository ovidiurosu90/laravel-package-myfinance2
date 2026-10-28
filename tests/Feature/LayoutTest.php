<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Support\Facades\View;
use ovidiuro\myfinance2\Tests\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Every MyFinance2 page extends the package's layout (myfinance2::layouts.app) and fills only its own sections; the
 * layout puts the page in the package's minimal page (no app layout configured: flash messages and the base assets
 * too) or in the app's layout, in the sections myfinance2.layout_sections names. Acts as a user with no data, never
 * saved; opens the accounts page.
 */
class LayoutTest extends TestCase
{
    private const MESSAGE = 'TST layout flash message';

    public function test_every_page_extends_the_package_layout_and_fills_only_its_sections(): void
    {
        $offending = [];
        $finder    = (new Finder())->files()->name('*.blade.php')
            ->in(__DIR__ . '/../../src/resources/views')->notPath(['layouts', 'emails']);
        foreach ($finder as $file) {
            $contents = $file->getContents();
            if (preg_match("/@extends\((?!'myfinance2::layouts\.app'\))/", $contents)
                || preg_match("/@section\('(?!myfinance2_)/", $contents)
            ) {
                $offending[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offending, "Pages not on myfinance2::layouts.app or its sections:\n"
            . implode("\n", $offending));
    }

    public function test_without_an_app_layout_a_page_renders_in_the_package_page_with_everything(): void
    {
        $html = $this->_accountsPage();

        $this->assertStringContainsString('<title>' . trans('myfinance2::accounts.titles.dashboard') . ' | ', $html);
        $this->assertStringContainsString('navbar-dropdown-myfinance2', $html);
        $this->assertSame(1, substr_count($html, self::MESSAGE), 'The flash message is shown once');
        $this->assertSame(['myfinance2-base.css', 'myfinance2-base.js', 'myfinance2.css', 'myfinance2.js'],
            $this->_assets($html), 'Without an app bundle: jQuery / Bootstrap first');
    }

    public function test_in_an_app_layout_a_page_fills_the_sections_it_names(): void
    {
        View::addNamespace('tst', __DIR__ . '/../Fixtures/views');
        config([
            'myfinance2.layout'          => 'tst::host-layout',
            'myfinance2.layout_sections' => [
                'title' => 'page_title', 'styles' => 'head_extras', 'content' => 'main', 'scripts' => 'end_scripts',
            ],
            'myfinance2.flash_messages'  => false,
        ]);

        $html = $this->_accountsPage();

        $title = trans('myfinance2::accounts.titles.dashboard');
        $this->assertStringContainsString("<title>{$title}</title>", $html);
        $this->assertMatchesRegularExpression('#<div id="tst-host-layout">\s*\S#', $html, 'The page is in "main"');
        $this->assertSame(0, substr_count($html, self::MESSAGE), 'The app layout shows the flash messages');
        $this->assertSame(['myfinance2.css', 'myfinance2.js'], $this->_assets($html), 'The app brings jQuery');

        config(['myfinance2.base_assets' => true]);
        $this->assertSame(['myfinance2-base.css', 'myfinance2-base.js', 'myfinance2.css', 'myfinance2.js'],
            $this->_assets($this->_accountsPage()), 'An app layout without jQuery / Bootstrap');
    }

    private function _accountsPage(): string
    {
        $this->actingAs($this->userWithoutData());

        return $this->withSession(['success' => self::MESSAGE])
            ->get(route('myfinance2::accounts.index'))
            ->assertOk()
            ->getContent();
    }

    /**
     * The package's asset files the page loads (or names as missing), in order.
     *
     * @return list<string>
     */
    private function _assets(string $html): array
    {
        preg_match_all('#vendor/myfinance2/([\w-]+\.(?:css|js))#', $html, $matches);

        return array_values(array_unique($matches[1]));
    }
}
