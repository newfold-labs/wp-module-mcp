const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC';
const IMAGE_UPLOADS = new Set(['media-upload-1', 'uploading-image-19', 'uploading-media-file-28']);
const PRODUCT_42 = new Set(['wc-product-get-1', 'wc-product-update-1']);

export function resolveEvalPrompt(id: string, prompt: string, fixtures: { productId?: number; portfolioId?: number; testimonialId?: number; pageId?: number }): string {
  if (id === 'replacing-existing-image-25') {
    return `${prompt} Use a replacement 1-pixel PNG with file data data:image/png;base64,${TINY_PNG}.`;
  }
  if (IMAGE_UPLOADS.has(id)) {
    return `${prompt.replace(/test\.jpg/g, 'test.png')} The attached test image is a 1-pixel PNG. Its file data is data:image/png;base64,${TINY_PNG}.`;
  }
  if (id === 'adding-product-image-45') {
    return `Add a product image to the product from this flow. The image is a 1-pixel PNG with file data data:image/png;base64,${TINY_PNG}.`;
  }
  if (['inserting-media-into-existing-page-32', 'replacing-media-inside-page-33'].includes(id)) {
    if (!fixtures.pageId) throw new Error('Page fixture ID is required for this eval');
    return `${prompt} Use the published eval page with ID ${fixtures.pageId}.`;
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
  return prompt.replace(/test\.jpg/g, 'test.png');
}
