# Ysrtech_SimpleConfigurable

An OpenMage / Magento 1 module that simplifies the configurable product buying experience by letting customers select options and add products to the cart via AJAX — without leaving the page they're on (category/list pages, related products, etc.).

## Features

- **AJAX option selection** — configurable product options can be selected and added to cart without a full page navigation to the product view page.
- **Dynamic product details** — name, description, short description, additional attributes and main image update live as options are selected.
- **Dynamic pricing** — displays a "Price From:" range for configurable products and swaps in the exact price of the selected/cheapest matching child product.
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
   - `app/code/local/Ysrtech/SimpleConfigurable`
   - `app/etc/modules/Ysrtech_SimpleConfigurable.xml`
   - `design/frontend/base/default/layout/simpleconfigurable.xml`
   - `design/frontend/base/default/template/simpleconfigurable/`
   - `skin/frontend/base/default/js/simpleconfigurable/`
   - `skin/frontend/base/default/images/simpleconfigurable/`
2. Clear the Magento cache (`var/cache`) and, if applicable, compiled/merged JS/CSS.
3. Log in to the Admin Panel and re-index:
   - Catalog Search Index
   - Product Price
   - Stock Status
4. Go to **System > Configuration > Simple Configurable** to review the module settings.

## Configuration

The module adds a **Simple Configurable** section under **System > Configuration** with the following groups:

### General
- **Enabled** — master switch to enable/disable the module per store view.

### Cart
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

## License

Proprietary — © Ysrtech. All rights reserved unless stated otherwise by the repository owner.
