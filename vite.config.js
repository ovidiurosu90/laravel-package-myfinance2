import { defineConfig } from "vite";

/**
 * The package's prebuilt assets, so apps need no Node build for them: `yarn install && yarn build` writes dist/
 * (committed), which the service provider publishes to public/vendor/myfinance2 (tags myfinance2-assets and
 * laravel-assets). Two classic scripts (IIFE), one build each (--mode):
 * - main: dist/myfinance2.js / .css, the libraries only MyFinance2 pages use; uses the page's jQuery (window.jQuery)
 * - base: dist/myfinance2-base.js / .css, jQuery, Bootstrap, DataTables, selectize and Font Awesome, for pages
 *   without them (the standalone layout, or myfinance2.base_assets)
 */
const BUILDS = {
  main: {
    entry: "src/resources/assets/js/myfinance2.js",
    name: "myfinance2",
    external: ["jquery"],
  },
  base: {
    entry: "src/resources/assets/js/myfinance2-base.js",
    name: "myfinance2-base",
    external: [],
  },
};

export default defineConfig(({ mode }) => {
  const build = BUILDS[mode];
  if (!build) {
    throw new Error(`Unknown build "${mode}": vite build --mode main|base`);
  }

  return {
    publicDir: false,
    define: {
      "process.env.NODE_ENV": JSON.stringify("production"),
    },
    build: {
      outDir: "dist",
      emptyOutDir: false,
      lib: {
        entry: build.entry,
        formats: ["iife"],
        name: build.name.replace("-", "_"),
        fileName: () => `${build.name}.js`,
        cssFileName: build.name,
      },
      rollupOptions: {
        external: build.external,
        output: {
          globals: { jquery: "jQuery" },
        },
      },
    },
  };
});
