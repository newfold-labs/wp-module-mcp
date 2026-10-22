const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC';
const IMAGE_UPLOADS = new Set(['media-upload-1', 'uploading-image-19', 'uploading-media-file-28']);
const PRODUCT_42 = new Set(['wc-product-get-1', 'wc-product-update-1']);
const STANDALONE_IDS: Record<string, [string, number]> = {
  'post-get-1': ['post_get', 42],
  'post-delete-1': ['post_delete', 99],
  'page-get-1': ['page_get', 10],
  'page-update-1': ['page_update', 7],
  'page-delete-1': ['page_delete', 23],
  'media-get-1': ['media_get', 34],
  'media-file-1': ['media_get', 34],
  'media-update-1': ['media_update', 55],
  'media-delete-1': ['media_delete', 88],
  'user-get-1': ['user_get', 3],
  'user-update-1': ['user_update', 4],
  'user-delete-1': ['user_delete', 5],
  'category-update-1': ['category_update', 8],
  'category-delete-1': ['category_delete', 12],
  'tag-update-1': ['tag_update', 5],
  'tag-delete-1': ['tag_delete', 9],
  'wc-product-delete-1': ['wc_product_delete', 150],
  'wc-category-delete-1': ['wc_category_delete', 20],
  'wc-tag-update-1': ['wc_tag_update', 7],
  'wc-tag-delete-1': ['wc_tag_delete', 11],
  'wc-brand-update-1': ['wc_brand_update', 3],
  'wc-brand-delete-1': ['wc_brand_delete', 6],
  'global-styles-get-1': ['global_styles_get', 128],
};

export function resolveEvalPrompt(id: string, prompt: string, fixtures: { productId?: number; portfolioId?: number; testimonialId?: number; pageId?: number; ids?: Record<string, number> }): string {
  const standalone = STANDALONE_IDS[id];
  if (standalone) {
    const [key, placeholder] = standalone;
    const fixtureId = fixtures.ids?.[key];
    if (!fixtureId) throw new Error(`${key} fixture ID is required for this eval`);
    return prompt.replace(new RegExp(`\\b${placeholder}\\b`, 'g'), String(fixtureId));
  }
  if (id === 'replacing-existing-image-25') {
    return `${prompt} Use a replacement image named replacement.png with file data data:image/png;base64,${TINY_PNG}.`;
  }
  if (IMAGE_UPLOADS.has(id)) {
    const filename = id === 'uploading-media-file-28' ? 'media-flow.png' : 'test.png';
    return `${prompt.replace(/test\.jpg/g, filename)} The attached test image is a 1-pixel PNG. Its file data is data:image/png;base64,${TINY_PNG}.`;
  }
  if (id === 'adding-product-image-45') {
    return `Upload this 1-pixel PNG as product-image.png, then add the uploaded media attachment to the product from this flow using its attachment ID. File data: data:image/png;base64,${TINY_PNG}.`;
  }
  if (id === 'setting-homepage-68') {
    const homepageId = fixtures.ids?.settings_homepage;
    if (!homepageId) throw new Error('Settings homepage fixture ID is required for this eval');
    return `Set homepage to page ID ${homepageId} (AI Eval Settings Home).`;
  }
  if (['inserting-media-into-existing-page-32', 'replacing-media-inside-page-33'].includes(id)) {
    if (!fixtures.pageId) throw new Error('Page fixture ID is required for this eval');
    if (id === 'replacing-media-inside-page-33') {
      return `On page ID ${fixtures.pageId}, replace the existing media-flow.png image with media-replacement.png. The replacement file data is data:image/png;base64,${TINY_PNG}.`;
    }
    return `Add the uploaded media-flow.png image to the content of the published page with ID ${fixtures.pageId}.`;
  }
  if (['uploading-unsupported-file-36', 'verifying-invalid-file-rejection-37'].includes(id)) {
    return `${prompt} The file data is base64: TVoAAAEAAAA=.`;
  }
  if (PRODUCT_42.has(id)) {
    if (!fixtures.productId) throw new Error('Product fixture ID is required for this eval');
    return prompt.replace(/\bID 42\b/g, `ID ${fixtures.productId}`);
  }
  if (['cpt-get-1', 'cpt-update-1'].includes(id)) {
    if (!fixtures.portfolioId) throw new Error('Portfolio fixture ID is required for this eval');
    return prompt.replace(/\bID 25\b/g, `ID ${fixtures.portfolioId}`);
  }
  if (id === 'cpt-delete-1') {
    if (!fixtures.testimonialId) throw new Error('Testimonial fixture ID is required for this eval');
    return prompt.replace(/\bID 30\b/g, `ID ${fixtures.testimonialId}`);
  }
  const mediaFlow = /-(?:29|30|31|32|33|34|35)$/.test(id);
  return prompt.replace(/test\.jpg/g, mediaFlow ? 'media-flow.png' : 'test.png');
}
