## :red_circle: MCP AI Eval Results

**Model:** `openai/gpt-4o-mini` | **MCP tools:** 91 (live) | **Passed:** 79 | **Failed:** 4 | **Errors:** 0 | **Skipped:** 0 | **Pass Rate:** 95%

| Status | Test | Expected Tool | Actual Tool |
|--------|------|---------------|-------------|
| :white_check_mark: | Get site overview | `blu_get-site-info` | `blu-get-site-info` |
| :white_check_mark: | WordPress version check | `blu_get-site-info` | `blu-get-site-info` |
| :white_check_mark: | Active theme query | `blu_get-active-theme` | `blu-get-active-theme` |
| :white_check_mark: | Get site settings | `blu_get-general-settings` | `blu-get-general-settings` |
| :white_check_mark: | Update site title | `blu_update-general-settings` | `blu-update-general-settings` |
| :white_check_mark: | Search blog posts | `blu_posts-search` | `blu-posts-search` |
| :white_check_mark: | List draft posts | `blu_posts-search` | `blu-posts-search` |
| :white_check_mark: | Get specific post | `blu_get-post` | `blu-get-post` |
| :white_check_mark: | Create new blog post | `blu_add-post` | `blu-add-post` |
| :white_check_mark: | Update existing post | `blu_update-post` | `blu-update-post` |
| :white_check_mark: | Delete a post | `blu_delete-post` | `blu-delete-post` |
| :white_check_mark: | List post categories | `blu_list-categories` | `blu-list-categories` |
| :white_check_mark: | Add post category | `blu_add-category` | `blu-add-category` |
| :white_check_mark: | List post tags | `blu_list-tags` | `blu-list-tags` |
| :white_check_mark: | Search pages | `blu_pages-search` | `blu-pages-search` |
| :x: | Create a page | `blu_add-page` | No tool called |
| :white_check_mark: | Delete a page | `blu_delete-page` | `blu-delete-page` |
| :white_check_mark: | List media items | `blu_list-media` | `blu-list-media` |
| :white_check_mark: | Search media by keyword | `blu_search-media` | `blu-search-media` |
| :x: | Upload media file | `blu_upload-media` | No tool called |
| :white_check_mark: | Delete media item | `blu_delete-media` | `blu-delete-media` |
| :white_check_mark: | List all users | `blu_users-search` | `blu-users-search` |
| :white_check_mark: | Create new user | `blu_add-user` | `blu-add-user` |
| :white_check_mark: | Get current user | `blu_get-current-user` | `blu-get-current-user` |
| :white_check_mark: | Delete a user | `blu_delete-user` | `blu-delete-user` |
| :white_check_mark: | List REST API endpoints | `blu_list-api-functions` | `blu-list-api-functions` |
| :white_check_mark: | Get API endpoint details | `blu_get-function-details` | `blu-get-function-details` |
| :white_check_mark: | List post types | `blu_list-post-types` | `blu-list-post-types` |
| :white_check_mark: | Search custom post type items | `blu_cpt-search` | `blu-cpt-search` |
| :white_check_mark: | Get active global styles | `blu_get-active-global-styles` | `blu-get-active-global-styles` |
| :white_check_mark: | Search WooCommerce products | `blu_wc-products-search` | `blu-wc-products-search` |
| :white_check_mark: | Add WooCommerce product | `blu_wc-add-product` | `blu-wc-add-product` |
| :white_check_mark: | Delete WooCommerce product | `blu_wc-delete-product` | `blu-wc-delete-product` |
| :white_check_mark: | List product categories | `blu_wc-list-product-categories` | `blu-wc-list-product-categories` |
| :white_check_mark: | List product tags | `blu_wc-list-product-tags` | `blu-wc-list-product-tags` |
| :white_check_mark: | List product brands | `blu_wc-list-product-brands` | `blu-wc-list-product-brands` |
| :white_check_mark: | Search WooCommerce orders | `blu_wc-orders-search` | `blu-wc-orders-search` |
| :white_check_mark: | Get sales report | `blu_wc-reports-sales` | `blu-wc-reports-sales` |
| :white_check_mark: | Get customers report | `blu_wc-reports-customers-totals` | `blu-wc-reports-customers-totals` |
| :white_check_mark: | Get orders report | `blu_wc-reports-orders-totals` | `blu-wc-reports-orders-totals` |
| :white_check_mark: | Suggest product description | `blu_suggest-product-description` | `blu-suggest-product-description` |
| :white_check_mark: | Improve product description | `blu_improve-product-description` | `blu-improve-product-description` |
| :white_check_mark: | Suggest product categories | `blu_suggest-product-categories` | `blu-suggest-product-categories` |
| :white_check_mark: | Suggest product tags | `blu_suggest-product-tag` | `blu-suggest-product-tag` |
| :white_check_mark: | Smart product details | `blu_smart-product-details` | `blu-smart-product-details` |
| :white_check_mark: | Suggest variation attributes | `blu_suggest-product-variation-attributes` | `blu-suggest-product-variation-attributes` |
| :white_check_mark: | Update post category | `blu_update-category` | `blu-update-category` |
| :white_check_mark: | Delete post category | `blu_delete-category` | `blu-delete-category` |
| :white_check_mark: | Add post tag | `blu_add-tag` | `blu-add-tag` |
| :white_check_mark: | Update post tag | `blu_update-tag` | `blu-update-tag` |
| :white_check_mark: | Delete post tag | `blu_delete-tag` | `blu-delete-tag` |
| :white_check_mark: | Get specific page | `blu_get-page` | `blu-get-page` |
| :white_check_mark: | Update a page | `blu_update-page` | `blu-update-page` |
| :white_check_mark: | Get media item details | `blu_get-media` | `blu-get-media` |
| :white_check_mark: | Get media file content | `blu_get-media-file` | `blu-get-media-file` |
| :white_check_mark: | Update media item | `blu_update-media` | `blu-update-media` |
| :white_check_mark: | Get specific user | `blu_get-user` | `blu-get-user` |
| :white_check_mark: | Update a user | `blu_update-user` | `blu-update-user` |
| :white_check_mark: | Update current user profile | `blu_update-current-user` | `blu-update-current-user` |
| :x: | Run REST API function | `blu_run-api-function` | `blu-list-api-functions` |
| :x: | Get custom post type item | `blu_get-cpt` | `blu-get-post` |
| :white_check_mark: | Add custom post type item | `blu_add-cpt` | `blu-add-cpt` |
| :white_check_mark: | Update custom post type item | `blu_update-cpt` | `blu-update-cpt` |
| :white_check_mark: | Delete custom post type item | `blu_delete-cpt` | `blu-delete-cpt` |
| :white_check_mark: | Get global styles by ID | `blu_get-global-styles` | `blu-get-global-styles` |
| :white_check_mark: | Update global styles | `blu_update-global-styles` | `blu-update-global-styles` |
| :white_check_mark: | Get active global styles ID | `blu_get-active-global-styles-id` | `blu-get-active-global-styles-id` |
| :white_check_mark: | Get WooCommerce product | `blu_wc-get-product` | `blu-wc-get-product` |
| :white_check_mark: | Update WooCommerce product | `blu_wc-update-product` | `blu-wc-update-product` |
| :white_check_mark: | Add WooCommerce product category | `blu_wc-add-product-category` | `blu-wc-add-product-category` |
| :white_check_mark: | Update WooCommerce product category | `blu_wc-update-product-category` | `blu-wc-update-product-category` |
| :white_check_mark: | Delete WooCommerce product category | `blu_wc-delete-product-category` | `blu-wc-delete-product-category` |
| :white_check_mark: | Add WooCommerce product tag | `blu_wc-add-product-tag` | `blu-wc-add-product-tag` |
| :white_check_mark: | Update WooCommerce product tag | `blu_wc-update-product-tag` | `blu-wc-update-product-tag` |
| :white_check_mark: | Delete WooCommerce product tag | `blu_wc-delete-product-tag` | `blu-wc-delete-product-tag` |
| :white_check_mark: | Add WooCommerce product brand | `blu_wc-add-product-brand` | `blu-wc-add-product-brand` |
| :white_check_mark: | Update WooCommerce product brand | `blu_wc-update-product-brand` | `blu-wc-update-product-brand` |
| :white_check_mark: | Delete WooCommerce product brand | `blu_wc-delete-product-brand` | `blu-wc-delete-product-brand` |
| :white_check_mark: | Get coupons report | `blu_wc-reports-coupons-totals` | `blu-wc-reports-coupons-totals` |
| :white_check_mark: | Get products report | `blu_wc-reports-products-totals` | `blu-wc-reports-products-totals` |
| :white_check_mark: | Get reviews report | `blu_wc-reports-reviews-totals` | `blu-wc-reports-reviews-totals` |
| :white_check_mark: | Search Google product taxonomy | `blu_google-product-taxonomy` | `blu-google-product-taxonomy` |
| :white_check_mark: | Suggest product brands | `blu_suggest-product-brand` | `blu-suggest-product-brand` |