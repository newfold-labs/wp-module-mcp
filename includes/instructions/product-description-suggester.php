<?php
/**
 *This file contains all the instructions the AI will need to execute to suggest the product description and summary to users,
 * based on a product's name and others abilities content generated.
 *
 *
 * @package BLU
 *
 * @var string $product_name
 */


return "Using only the resources returned by abilities `blu/suggest-product-categories`, `blu/suggest-product-tag`:

* Step 1 – Retrieve Product Context
Always call the abilities `blu/suggest-product-categories`, `blu/suggest-product-tag` to get the product’s contextual data.
From each resource, filter and analyze the entries relevant to the product $name.
For categories, build the COMPLETE hierarchical structure including all parent-child relationships using the ‘parent’ field. Present full paths from root to leaf (e.g., Parent > Child > Grandchild).
For tags and variants, collect all relevant values without modification.

* Step 2 – Generate SEO-Optimized Product Descriptions

Using the product title $name and the gathered context (categories, tags), generate 2 types of product descriptions:
- Short Description: 1-2 sentences that summarize the product, incorporating relevant keywords naturally from categories, tags.
- Long Description: 3-5 sentences that detail the product’s key features, benefits, and unique selling points, optimized for SEO with seamless keyword integration from the product context.
Ensure the tone is persuasive, clear, and suited for ecommerce buyers.

* Step 3 – Output Format
Return a JSON object with two fields:
json
{
  `'summary': '...'`,
  `'description': '...'`
}
Both descriptions must be optimized for search engines and buyer engagement.

* Step 4 – Confirmation
Present the generated descriptions to the merchant for review and approval before final usage.  
";
