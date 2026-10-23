interface Product {
  name?: string;
  status?: string;
  categories?: Array<{ name?: string }>;
  tags?: Array<{ name?: string }>;
}

export function pageHasImage(content: string): boolean {
  return /<img\b|<!--\s+wp:image\b/i.test(content);
}

/** Identify each image by attachment ID or source URL so a text-only edit is not read as a replacement. */
export function pageImageKeys(content: string): Set<string> {
  const keys = new Set<string>();
  for (const match of content.matchAll(/<!--\s+wp:image\s+(\{.*?\})\s*\/?-->/gis)) {
    try {
      const id = (JSON.parse(match[1]) as { id?: unknown }).id;
      if (typeof id === 'number') keys.add(`id:${id}`);
    } catch { /* fall back to the img source */ }
  }
  for (const match of content.matchAll(/<img\b[^>]*?\bsrc=["']([^"']+)["']/gi)) keys.add(`src:${match[1]}`);
  return keys;
}

/** A replacement removes at least one prior image and adds at least one new one. */
export function pageImageReplaced(before: string, after: string): boolean {
  const prior = pageImageKeys(before);
  const current = pageImageKeys(after);
  return [...current].some((key) => !prior.has(key)) && [...prior].some((key) => !current.has(key));
}

export function productHasTerms(product: Product, category: string, tag: string): boolean {
  return Boolean(product.categories?.some((item) => item.name === category)
    && product.tags?.some((item) => item.name === tag));
}

/** Read-only WordPress REST checks for stateful AI eval outcomes. */
export class WordPressOutcomes {
  private readonly origin: string;
  private readonly auth: string;

  constructor(serverUrl: string, authorization: string) {
    this.origin = new URL(serverUrl).origin;
    this.auth = authorization;
  }

  private async get<T>(route: string, params: Record<string, string> = {}): Promise<T> {
    const url = new URL('/index.php', this.origin);
    url.searchParams.set('rest_route', route);
    for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value);
    const response = await fetch(url, { headers: { Authorization: this.auth } });
    if (!response.ok) throw new Error(`WordPress outcome request ${route} returned HTTP ${response.status}`);
    return await response.json() as T;
  }

  /** Drafts have no slug until published, so match the exact title instead. */
  private async landingPageId(): Promise<number | undefined> {
    const title = 'AI Landing Page';
    const pages = await this.get<Array<{ id: number; title?: { raw?: string } }>>('/wp/v2/pages', {
      search: title, status: 'any', context: 'edit', orderby: 'date', order: 'desc', per_page: '100',
    });
    return pages.find((page) => page.title?.raw?.trim() === title)?.id;
  }

  async pageContent(pageId?: number): Promise<string> {
    const id = pageId ?? await this.landingPageId();
    if (!id) return '';
    const page = await this.get<{ content?: { raw?: string; rendered?: string } }>(`/wp/v2/pages/${id}`, { context: 'edit' });
    return page.content?.raw ?? page.content?.rendered ?? '';
  }

  async pageStatus(pageId?: number): Promise<string | undefined> {
    const id = pageId ?? await this.landingPageId();
    if (!id) return undefined;
    const page = await this.get<{ status?: string }>(`/wp/v2/pages/${id}`, { context: 'edit' });
    return page.status;
  }

  async product(name = 'AI Tool'): Promise<Product | undefined> {
    const products = await this.get<Product[]>('/wc/v3/products', { search: name, per_page: '100' });
    return products.find((item) => item.name === name);
  }
}
