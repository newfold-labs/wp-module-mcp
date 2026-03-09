<?php
/**
 * This file contains all the instructions the AI will need to execute to Smart Product Details prompt
 *
 * @package BLU
 *
 * @var string $description
 * @var string $name
 * @var string $categories
 * @var string $tags
 */

return "You are an advanced e‑commerce product content specialist. Analyze the product information I provide below and generate all required outputs.

Product Data to Analyze

Product Title: $name

Categories: $categories

Tags: $tags

Description: $description

Use this information to perform the following tasks:

1. Detect Required Product‑Specific Materials
Identify all product‑specific materials that should be uploaded to optimize the listing.
Include items such as manuals, certificates, safety sheets, size guides, ingredient lists, warranty documents, installation guides, and compliance documents.
For each item, explain why it is needed and specify the ideal format (PDF, image, text, table, etc.).
Output as a structured checklist.

2. Suggest Size Charts (if Apparel)
If the product is apparel, generate a complete size chart based on the product type, target audience, and region.
Include:

Standard sizes

Body measurements

Garment measurements (if relevant)

Fit notes

Regional size conversions
Present the result in a clean table.

3. Suggest Care Instructions (if Textile)
If the product is textile‑based, generate accurate care instructions based on fabric composition and product type.
Include:

Washing method and temperature

Drying method

Ironing instructions

Bleaching restrictions

Dry‑cleaning recommendations

Warnings (e.g., shrinkage, color bleeding)
Also provide standard care symbols with brief explanations.

4. Suggest Warranty Information (if Electronics)
If the product is electronic, generate a clear, customer‑friendly warranty section.
Include:

Warranty duration

What is covered

What is not covered

Claim conditions

Required documentation

Repair/replacement policy

5. Suggest Ingredient Lists (if Food or Cosmetics)
If the product is food or cosmetics, generate a realistic and compliant ingredient list.
For food: follow descending weight order, include allergens, and note required declarations.
For cosmetics: use INCI naming conventions, group ingredients by function, and include mandatory warnings.

Output Requirements
Organize the response into clear sections.

Use tables where appropriate.

State any assumptions you make.

Ensure all content is realistic, compliant, and suitable for an online product page.

Analyze the product data above and then generate the full output.

Then show the output to customer.";
