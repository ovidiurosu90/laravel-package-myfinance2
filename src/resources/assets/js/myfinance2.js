/**
 * The libraries only MyFinance2 pages use, as the globals their inline scripts call: LightweightCharts (charts),
 * TempusDominus (date pickers) and $.fn.bootstrapToggle (toggles, also on data-bs-toggle="toggle").
 *
 * Built into dist/myfinance2.js / .css (yarn build) and published to public/vendor/myfinance2. jQuery is the page's
 * own (window.jQuery, external): the app's bundle or dist/myfinance2-base.js, loaded before this one.
 */
import * as LightweightCharts from "lightweight-charts";
import { TempusDominus } from "@eonasdan/tempus-dominus";
import "@eonasdan/tempus-dominus/dist/css/tempus-dominus.css";
import "./vendor/bootstrap5-toggle.jquery.js";
import "bootstrap5-toggle/css/bootstrap5-toggle.min.css";

window.LightweightCharts = LightweightCharts;
window.TempusDominus = TempusDominus;
