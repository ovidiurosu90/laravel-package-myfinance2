/**
 * What MyFinance2 pages expect from the page around them: jQuery, Bootstrap (and its jQuery tooltip plugin),
 * DataTables, selectize and Font Awesome. Loaded by the package's standalone page, or with an app layout that lacks
 * them (myfinance2.base_assets); always before dist/myfinance2.js.
 *
 * Built into dist/myfinance2-base.js / .css (yarn build) and published to public/vendor/myfinance2.
 */
import jQuery from "jquery";
import * as bootstrap from "bootstrap";
import "bootstrap/dist/css/bootstrap.min.css";
import DataTable from "datatables.net-bs5";
import "datatables.net-bs5/css/dataTables.bootstrap5.min.css";
import "@selectize/selectize/dist/js/selectize.min.js";
import "@selectize/selectize/dist/css/selectize.bootstrap5.css";
import "@fortawesome/fontawesome-free/css/all.min.css";

window.jQuery = window.$ = jQuery;
window.bootstrap = bootstrap;
window.DataTable = DataTable;

// Bootstrap adds its jQuery plugins only on DOMContentLoaded, after the pages' inline scripts call $(...).tooltip()
const tooltip = bootstrap.Tooltip;
const previous = jQuery.fn[tooltip.NAME];
jQuery.fn[tooltip.NAME] = tooltip.jQueryInterface;
jQuery.fn[tooltip.NAME].Constructor = tooltip;
jQuery.fn[tooltip.NAME].noConflict = () =>
{
    jQuery.fn[tooltip.NAME] = previous;
    return tooltip.jQueryInterface;
};
