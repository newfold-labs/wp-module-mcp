<?php
/**
 * This file contains all the instructions the AI will need to execute to suggest the product description and summary to users,
 * based on a product's name and others abilities content generated.
 *
 * @package BLU
 *
 * @var string $name
 */

return "Use the resources returned by abilities blu/suggest-product-categories, blu/suggest-product-tag only if they have been previously provided or are present in the product data:

Step 1 – Retrieve Product Context
Always use the product title $name.
If available or previously suggested, incorporate categories and tags returned by blu/suggest-product-categories and blu/suggest-product-tag.
For categories, build the COMPLETE hierarchical structure including all parent-child relationships using the ‘parent’ field. Present full paths from root to leaf (e.g., Parent > Child > Grandchild).
For tags and variants, collect all relevant values without modification.
If categories or tags are not present, proceed using only the product title.

Step 2 – Generate SEO-Optimized Product Descriptions
Using the product title $name and any gathered context (categories, tags):

Generate Short Description: 1-2 sentences summarizing the product, naturally incorporating relevant keywords from categories and tags if available.
Generate Long Description: 3-5 sentences detailing key features, benefits, and unique selling points, SEO-optimized with keyword integration from available context.
Use a persuasive, clear tone suited for ecommerce buyers.

Step 3 – Output Format
Return a JSON object with two fields:
json
{
  `'short_description': '...'`,
  `'description': '...'`
}
Both descriptions must be optimized for search engines and buyer engagement.

* Step 4 – Confirmation
Present the generated descriptions to the merchant for review and approval before final usage.  
";
