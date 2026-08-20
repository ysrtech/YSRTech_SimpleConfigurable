# YSRTech_SimpleConfigurable

An OpenMage / Magento 1 module that simplifies the configurable product buying experience by letting customers select options and add products to the cart via AJAX — without leaving the page they're on (category/list pages, related products, etc.).

## Features

- **AJAX option selection** — configurable product options can be selected and added to cart without a full page navigation to the product view page.
- **Dynamic product details** — name, description, short description, additional attributes and main image update live as options are selected.
- **Price ranges** — a configurable shows the span across its associated products ("$107.00 - $130.00") for the regular price and, where a special price or catalog price rule applies, for the sale price too. Once a selection narrows to a single child the exact price replaces the range; clearing the selection brings it back.
- **Stock-aware pricing** — price calculation is delegated to the cheapest **in-stock** associated simple product.
- **Custom options support** — child products' own custom options are loaded via AJAX once all configurable attributes are selected.
- **Optional full image gallery swap** — update the entire product gallery (not just the main image) when a child is selected.
- **Configurable cart display** — choose whether the cart shows the parent configurable product or the selected child (name, image, options, custom options).
- **Price range in option dropdowns** — optionally show the min-max price for each selectable attribute value.

## Requirements

This module depends on the following core modules:

- `Mage_Catalog`
- `Mage_CatalogIndex`
- `Mage_CatalogInventory`
- `Mage_CatalogRule`
- `Mage_Checkout`

## Installation

1. Copy the contents of this repository into your OpenMage/Magento 1 root directory, preserving the folder structure:
   - `app/code/local/YSRTech/SimpleConfigurable`
   - `app/etc/modules/YSRTech_SimpleConfigurable.xml`
   - `design/frontend/base/default/layout/simpleconfigurable.xml`
   - `design/frontend/base/default/template/simpleconfigurable/`
   - `skin/frontend/base/default/js/simpleconfigurable/`
   - `skin/frontend/base/default/images/simpleconfigurable/`
2. Clear the Magento cache (`var/cache`) and, if applicable, compiled/merged JS/CSS.
3. Log in to the Admin Panel and re-index:
   - Catalog Search Index
   - Product Price
   - Stock Status

   Re-indexing is required, not optional: the price and stock-status indexer rewrites change
   indexed values store-wide.
4. Go to **System > Configuration > Catalog > Simple Configurable Config** to review the module settings.

### Backing it out

`simpleconfigurable/general/enabled` gates the product model, the configurable type/price models, the configurable view block and the cart-line-edit observer — but **not** the price indexer, the stock-status indexer, the catalog price rule resource or the product collection price joins. Those follow the module being active at all. To fully revert, set `<active>false</active>` in `app/etc/modules/YSRTech_SimpleConfigurable.xml` and re-index Product Price and Stock Status. Worth staging against a copy of production data first.

## Configuration

The module adds a **Simple Configurable Config** section under **System > Configuration > Catalog** with the following groups:

### General
- **Enabled** — master switch to enable/disable the module per store view.

### Cart
- **Add Configurable Product To Cart Instead Of Child** — Yes uses the standard OpenMage behaviour: the configurable is added with its chosen options, so the cart, order and invoice carry the parent SKU. No adds the matching child on its own, which is this module's original behaviour and the default. Child products' own custom options only apply when this is No.
- **Show Configurable Product Options** — show the selected attribute values as options in the cart.
- **Show Custom Options** — show the child product's custom options in the cart.
- **Show Configurable Product Name** — display the parent configurable product's name instead of the child's.
- **Show Configurable Product Image** — display the parent configurable product's image instead of the child's.

### Product Page
- **Change Name / Description / Short Description / Attributes** — dynamically update these fields on the product page as options are selected.
- **Change Image** — dynamically update the main product image.
- **Change Image (Fancy/Gallery)** — update the full image gallery via AJAX (requires *Change Image*).
- **Show Price Ranges in Options** — display the min-max price range next to each attribute option.
- **Set Price Is Lowest Price** — display the configurable product's price as the cheapest available child's price.

## How It Works

- The module rewrites core catalog, cart, and indexing classes to let configurable products defer pricing, stock, and visibility decisions to their associated simple products.
- A dedicated frontend router (`scp`) exposes AJAX endpoints used to fetch a selected child product's custom options, main image, and full gallery on demand.
- A JavaScript extension (`skin/frontend/base/default/js/simpleconfigurable/product_extension.js`) enhances Magento's `Product.Config` to resolve the matching (or cheapest in-scope) simple product for the current selection and refresh the page content accordingly.
- Selected child products are added to the cart along with a reference to their configurable parent (`cpid`), which the cart item renderer uses to decide what to display.
- Editing such a cart line re-opens the **parent** configurable rather than the child. This is done from an observer on `controller_action_predispatch_checkout_cart_configure` rather than by overriding `Mage_Checkout_CartController`: `Mage_Core_Controller_Varien_Action::preDispatch()` names that event after the route, not the controller class, so it fires whichever module ends up owning `checkout/cart`. A controller override would have to be routed ahead of every other extension that injects its own `CartController` (OneStepCheckout and similar), and would silently do nothing on any store where one of them wins that ordering.

## Module layout

Standard Magento 1 structure — class names map straight onto paths under `app/code/local/YSRTech/SimpleConfigurable/`:

```
Block/    Adminhtml/  Catalog/  Checkout/     rewritten blocks, grouped by the core module they replace
Model/    Catalog/  CatalogIndex/  CatalogInventory/  CatalogRule/   rewritten models, same grouping
          Observer.php                        frontend observers
Helper/   Data.php                            config accessors
controllers/  AjaxController.php              the `scp` AJAX endpoints
etc/      config.xml  system.xml  adminhtml.xml
```

Config groups are declared as `ysrtech_simpleconfigurable` (`Mage::helper('ysrtech_simpleconfigurable')`, `Mage::getModel('ysrtech_simpleconfigurable/observer')`).

## Theming

The live-update helpers in `product_extension.js` find the page regions they rewrite through `Product.Config.prototype.scpTargets`, a per-region list of selectors tried in order. Defaults cover the core `base/default` markup plus a few widely-reused variants. If your theme names a region differently, repoint it from your own skin JS instead of forking the file:

```js
Product.Config.prototype.scpTargets.productName = ['#my-theme-title'];
```

A region your theme does not have resolves to an empty list, and the corresponding helper no-ops rather than throwing.

**The product-page features need the standard options blocks.** They hook `Product.Config`, which is constructed by `catalog/product/view/type/options/configurable.phtml` — rendered through `product.info.options.wrapper` via `container1`/`container2` in `catalog/product/view.phtml`. A theme whose `view.phtml` builds its own configurable UI and never outputs those containers will load this module's JS but have nothing for it to attach to, and `#SCPcustomOptionsDiv` will not exist. The PHP-side behaviour (pricing, indexing, catalog rules, cart display, cart-line editing, the admin grid) is unaffected by theming.

## License

Proprietary — © YSRTech. All rights reserved unless stated otherwise by the repository owner.
