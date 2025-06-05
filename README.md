# Airtable WooCommerce Sync

**Version:** 0.1.1
**Contributors:** AI Developer
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to synchronize variable products from an Airtable base to WooCommerce.

## Description

This plugin allows WooCommerce store owners to manage their variable product inventory in Airtable and sync it manually to their WordPress/WooCommerce store. It handles:

*   Variable products.
*   Product attributes (e.g., Color, Size).
*   Individual product variations with their own SKU, price, and stock quantity.

This plugin now uses **Airtable Personal Access Tokens** for more secure API access, aligning with Airtable's latest authentication recommendations. It is intended for users who are comfortable with structuring data in Airtable, particularly using JSON for defining product attributes and variations within text fields.

## Installation

1.  Download the plugin ZIP file.
2.  Log in to your WordPress admin panel.
3.  Navigate to **Plugins > Add New**.
4.  Click on **Upload Plugin** and select the downloaded ZIP file.
5.  Activate the plugin through the 'Plugins' menu in WordPress.

## Configuration

1.  After activation, navigate to **Airtable Sync** from the main WordPress admin menu.
2.  Enter your **Airtable Personal Access Token**. Airtable is transitioning to Personal Access Tokens for enhanced security. You can generate these tokens from your Airtable account page (usually under Developer Hub or Account settings). Make sure to grant the token the necessary permissions (e.g., `data.records:read`, `data.records:write`, `schema.bases:read`).
3.  Enter your **Airtable Base ID**. This can be found in the API documentation for your base on Airtable (often in the URL when viewing API docs, or on the introduction page).
4.  Enter the **Airtable Table Name** that contains your product information (e.g., "Products").
5.  Click **Save Settings**.

## Usage

### Airtable Data Structure

Your Airtable table needs to be structured correctly for the sync to work. Here are the key fields the plugin expects (case-sensitive):

*   `Name` (Single line text): The product title.
*   `SKU` (Single line text): The main SKU for the variable product. **This must be unique.**
*   `Description` (Long text): The main product description.
*   `ShortDescription` (Single line text): The product short description.
*   `ProductAttributes` (Long text): A JSON string defining the attributes for the variable product.
    *   Example: `[{"name": "Color", "options": ["Red", "Blue"], "is_visible": 1, "is_variation": 1}, {"name": "Size", "options": ["Small", "Medium"], "is_visible": 1, "is_variation": 1}]`
    *   `name`: The attribute name (e.g., "Color").
    *   `options`: An array of available terms for this attribute (e.g., `["Red", "Blue"]`).
    *   `is_visible`: `1` if visible on the product page, `0` if not.
    *   `is_variation`: `1` if used for variations, `0` if not.
*   `ProductVariations` (Long text): A JSON string defining each variation.
    *   Example: `[{"attributes": [{"name": "Color", "option": "Red"}, {"name": "Size", "option": "Small"}], "sku": "TSHIRT-R-S", "regular_price": "19.99", "stock_quantity": 10}, {"attributes": [{"name": "Color", "option": "Blue"}, {"name": "Size", "option": "Medium"}], "sku": "TSHIRT-B-M", "regular_price": "20.99", "stock_quantity": 15}]`
    *   `attributes`: An array of attribute name-option pairs for this specific variation.
    *   `sku`: The unique SKU for this variation.
    *   `regular_price`: The price for this variation.
    *   `stock_quantity`: The stock level for this variation (optional, if not set, stock won't be managed for the variation).

### Manual Synchronization

1.  Go to the **Airtable Sync** settings page in your WordPress admin.
2.  Click the **Start Manual Sync** button.
3.  The plugin will fetch data from your Airtable table and create or update products in WooCommerce.
4.  A status message will indicate the results of the sync (processed, created, updated, failed). Check server error logs (`error_log`) for more detailed debugging information if failures occur.

## Future Enhancements

*   Automatic scheduled synchronization (Cron job).
*   More flexible field mapping via the UI.
*   Image synchronization.
*   Support for other product types.

## Changelog

### 0.1.1
*   Updated authentication to use Airtable Personal Access Tokens (PATs) instead of API Keys, following Airtable's recommended security practices.
*   Updated admin settings UI and documentation to reflect the use of PATs.

### 0.1.0 (Initial Release)
*   Basic plugin structure.
*   Airtable API integration (read-only via API Key).
*   WooCommerce variable product creation and update logic.
*   Admin settings page for API credentials and table name.
*   Manual synchronization trigger.
*   Basic error handling and server-side logging.

```
