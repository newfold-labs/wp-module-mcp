# Recent MCP AI Evals: per-test selection results

Analyzed the ten most recent completed runs available on 2026-10-21. Seven executed model tests; three skipped every model test. The 273 observations below cover 118 of 178 defined cases; 60 cases had no observations. This is a strict expected-tool score from the historical reports; REST alternatives were still marked as failures in those runs. Each denominator is the number of runs that executed that case, not all ten workflow runs.

| Run | Model cases | Strict passes | Strict failures |
|---|---:|---:|---:|
| [#39703193527](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39703193527) | 28 | 13 | 15 |
| [#39700422691](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39700422691) | 28 | 11 | 17 |
| [#39700350261](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39700350261) | 15 | 5 | 10 |
| [#39663976875](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39663976875) | 98 | 66 | 32 |
| [#39650927987](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39650927987) | 98 | 64 | 34 |
| [#39602061220](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39602061220) | 0 | 0 | 0 |
| [#39599977138](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39599977138) | 0 | 0 | 0 |
| [#39484461237](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39484461237) | 0 | 0 | 0 |
| [#39307939527](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39307939527) | 3 | 3 | 0 |
| [#39176919179](https://github.com/newfold-labs/wp-module-mcp/actions/runs/39176919179) | 3 | 3 | 0 |

Historical `:warning:` rows were infrastructure/errors, not successful REST alternatives. There were no such error rows in the 273 observations. The changed-source filter means these rates are not a uniform sample of every ability.

## Always passed (67)

| Expected tool | Test | Failures / runs | Failure rate |
|---|---|---:|---:|
| `blu_add-category` | Add post category | 0/2 | 0% |
| `blu_add-category` | Creating a category | 0/2 | 0% |
| `blu_add-cpt` | Add custom post type item | 0/3 | 0% |
| `blu_add-page` | Creating a new page | 0/4 | 0% |
| `blu_add-post` | Creating a new post | 0/2 | 0% |
| `blu_add-tag` | Add post tag | 0/2 | 0% |
| `blu_add-tag` | Creating a tag | 0/2 | 0% |
| `blu_delete-category` | Delete post category | 0/2 | 0% |
| `blu_delete-media` | Delete media item | 0/2 | 0% |
| `blu_delete-page` | Delete a page | 0/2 | 0% |
| `blu_delete-post` | Attempting duplicate deletion | 0/2 | 0% |
| `blu_delete-post` | Deleting first post | 0/2 | 0% |
| `blu_delete-post` | Deleting the post | 0/2 | 0% |
| `blu_delete-tag` | Delete post tag | 0/2 | 0% |
| `blu_get-function-details` | Get API endpoint details | 0/2 | 0% |
| `blu_get-media` | Get media item details | 0/2 | 0% |
| `blu_get-media-file` | Get media file content | 0/2 | 0% |
| `blu_get-post` | Opening and verifying the post | 0/2 | 0% |
| `blu_list-api-functions` | List REST API endpoints | 0/2 | 0% |
| `blu_list-categories` | List post categories | 0/2 | 0% |
| `blu_list-media` | List media items | 0/2 | 0% |
| `blu_list-media` | Verifying media upload | 0/2 | 0% |
| `blu_list-post-types` | List post types | 0/3 | 0% |
| `blu_list-post-types` | Listing available post types | 0/3 | 0% |
| `blu_list-tags` | List post tags | 0/2 | 0% |
| `blu_posts-search` | Search blog posts | 0/2 | 0% |
| `blu_posts-search` | Searching for the post | 0/2 | 0% |
| `blu_posts-search` | Verifying post deletion | 0/2 | 0% |
| `blu_run-api-function` | Run REST API function | 0/2 | 0% |
| `blu_search-media` | Search media by keyword | 0/2 | 0% |
| `blu_search-media` | Searching uploaded media | 0/2 | 0% |
| `blu_update-category` | Update post category | 0/2 | 0% |
| `blu_update-media` | Update media item | 0/2 | 0% |
| `blu_update-page` | Adding page content | 0/4 | 0% |
| `blu_update-page` | Update a page | 0/2 | 0% |
| `blu_update-post` | Adding content to the post | 0/2 | 0% |
| `blu_update-post` | Assigning category and tag | 0/2 | 0% |
| `blu_update-post` | Publishing the post | 0/2 | 0% |
| `blu_update-post` | Renaming the post | 0/2 | 0% |
| `blu_update-post` | Saving the post as draft | 0/2 | 0% |
| `blu_update-post` | Unpublishing the post | 0/2 | 0% |
| `blu_update-post` | Update existing post | 0/2 | 0% |
| `blu_update-post` | Updating post content | 0/2 | 0% |
| `blu_update-tag` | Update post tag | 0/2 | 0% |
| `blu_upload-media` | Verifying invalid file rejection | 0/2 | 0% |
| `blu_wc-add-product` | Add WooCommerce product | 0/2 | 0% |
| `blu_wc-add-product` | Creating new product | 0/2 | 0% |
| `blu_wc-add-product-brand` | Add WooCommerce product brand | 0/2 | 0% |
| `blu_wc-add-product-category` | Add WooCommerce product category | 0/2 | 0% |
| `blu_wc-add-product-category` | Creating product category | 0/2 | 0% |
| `blu_wc-add-product-tag` | Add WooCommerce product tag | 0/2 | 0% |
| `blu_wc-add-product-tag` | Creating product tag | 0/2 | 0% |
| `blu_wc-delete-product-brand` | Delete WooCommerce product brand | 0/2 | 0% |
| `blu_wc-delete-product-category` | Delete WooCommerce product category | 0/2 | 0% |
| `blu_wc-delete-product-category` | Deleting category and tag | 0/2 | 0% |
| `blu_wc-delete-product-tag` | Delete WooCommerce product tag | 0/2 | 0% |
| `blu_wc-get-product` | Get WooCommerce product | 0/2 | 0% |
| `blu_wc-get-product` | Opening and verifying product details | 0/2 | 0% |
| `blu_wc-list-product-brands` | List product brands | 0/2 | 0% |
| `blu_wc-list-product-categories` | List product categories | 0/2 | 0% |
| `blu_wc-list-product-tags` | List product tags | 0/2 | 0% |
| `blu_wc-products-search` | Search WooCommerce products | 0/2 | 0% |
| `blu_wc-products-search` | Searching for the product | 0/2 | 0% |
| `blu_wc-products-search` | Verifying all product entities are removed | 0/2 | 0% |
| `blu_wc-update-product-brand` | Update WooCommerce product brand | 0/2 | 0% |
| `blu_wc-update-product-category` | Update WooCommerce product category | 0/2 | 0% |
| `blu_wc-update-product-tag` | Update WooCommerce product tag | 0/2 | 0% |

## Always failed (32)

| Expected tool | Test | Failures / runs | Failure rate |
|---|---|---:|---:|
| `blu_add-page` | Create a page | 2/2 | 100% |
| `blu_add-post` | Create new blog post | 2/2 | 100% |
| `blu_add-post` | Creating another post | 2/2 | 100% |
| `blu_add-section` | Inserting image into page | 4/4 | 100% |
| `blu_add-section` | Inserting media into existing page | 2/2 | 100% |
| `blu_delete-cpt` | Deleting Books item | 3/3 | 100% |
| `blu_delete-cpt` | Removing page from menu | 3/3 | 100% |
| `blu_delete-media` | Deleting media file | 2/2 | 100% |
| `blu_delete-page` | Deleting the page | 4/4 | 100% |
| `blu_edit-block` | Replacing existing image | 4/4 | 100% |
| `blu_edit-block` | Replacing media inside page | 2/2 | 100% |
| `blu_get-cpt` | Fetching Books item details | 3/3 | 100% |
| `blu_get-media` | Opening media details | 2/2 | 100% |
| `blu_get-page` | Get specific page | 2/2 | 100% |
| `blu_pages-search` | Verifying page removal | 4/4 | 100% |
| `blu_search-media` | Verifying media removal | 2/2 | 100% |
| `blu_update-cpt` | Updating Books item content | 3/3 | 100% |
| `blu_update-page` | Publishing the page | 4/4 | 100% |
| `blu_update-page` | Updating page content | 4/4 | 100% |
| `blu_update-post` | Scheduling the post | 2/2 | 100% |
| `blu_update-post` | Updating latest post | 2/2 | 100% |
| `blu_upload-media` | Upload media file | 2/2 | 100% |
| `blu_upload-media` | Uploading image | 4/4 | 100% |
| `blu_upload-media` | Uploading media file | 2/2 | 100% |
| `blu_upload-media` | Uploading unsupported file | 2/2 | 100% |
| `blu_wc-add-product` | Creating variable product | 2/2 | 100% |
| `blu_wc-get-product` | Verifying product variations | 2/2 | 100% |
| `blu_wc-update-product` | Adding product image | 2/2 | 100% |
| `blu_wc-update-product` | Adding product price and description | 2/2 | 100% |
| `blu_wc-update-product` | Assigning category and tag | 2/2 | 100% |
| `blu_wc-update-product` | Update WooCommerce product | 2/2 | 100% |
| `blu_wc-update-product` | Updating product price | 2/2 | 100% |

## Mixed (19)

| Expected tool | Test | Failures / runs | Failure rate |
|---|---|---:|---:|
| `blu_add-cpt` | Adding page to menu | 2/3 | 67% |
| `blu_add-cpt` | Creating and publishing Books custom post type item | 2/3 | 67% |
| `blu_add-post` | Creating first post | 1/2 | 50% |
| `blu_cpt-search` | Search custom post type items | 2/3 | 67% |
| `blu_cpt-search` | Searching Books custom post type item | 2/3 | 67% |
| `blu_cpt-search` | Verifying Books item deletion | 2/3 | 67% |
| `blu_delete-cpt` | Delete custom post type item | 1/3 | 33% |
| `blu_delete-post` | Delete a post | 1/2 | 50% |
| `blu_get-cpt` | Get custom post type item | 1/3 | 33% |
| `blu_get-page` | Opening and verifying page details | 2/4 | 50% |
| `blu_get-post` | Get specific post | 1/2 | 50% |
| `blu_pages-search` | Search pages | 1/2 | 50% |
| `blu_pages-search` | Searching for the page | 2/4 | 50% |
| `blu_posts-search` | List draft posts | 1/2 | 50% |
| `blu_update-cpt` | Update custom post type item | 1/3 | 33% |
| `blu_wc-delete-product` | Delete WooCommerce product | 1/2 | 50% |
| `blu_wc-delete-product` | Deleting the product | 1/2 | 50% |
| `blu_wc-update-product` | Publishing the product | 1/2 | 50% |
| `blu_wc-update-product` | Setting stock quantity | 1/2 | 50% |

## Interpretation

- A run marked successful by GitHub can still have model failures because the AI eval step is configured to continue on error.
- Many always-failing multi-step cases produced no relevant call; changing a tool description alone cannot establish missing prior state or supply an absent file.
- `Create new blog post` chose `POST /wp/v2/posts` in both observations. That is a plausible REST alternative, but the historical report did not record the REST response status, so it cannot be retroactively upgraded to a warning.
- Six Woo product prompt abilities were listed as missing MCP tools by the old coverage checker. They are MCP prompts, not tools, and belong in `prompts/list`. Fifteen block, image, logo, color, and document abilities genuinely lack model eval cases. Their tests need suitable document/image fixtures; adding selection-only prompts with unusable inputs would give misleading coverage.
