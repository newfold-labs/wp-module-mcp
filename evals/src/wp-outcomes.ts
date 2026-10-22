interface Product {
  name?: string;
  status?: string;
  categories?: Array<{ name?: string }>;
  tags?: Array<{ name?: string }>;
}

export function pageHasImage(content: string): boolean {
  return /<img\b|<!--\s+wp:image\b/i.test(content);
}

export function productHasTerms(product: Product, category: string, tag: string): boolean {
  return Boolean(product.categories?.some((item) => item.name === category)
    && product.tags?.some((item) => item.name === tag));
}

/** Read-only WordPress REST checks for stateful AI eval outcomes. */
export class WordPressOutcomes {
  private readonly origin: string;
  private readonly auth: string;

  constructor(serverUrl: string, user: string, password: string) {
    this.origin = new URL(serverUrl).origin;
    this.auth = `Basic ${Buffer.from(`${user}:${password}`).toString('base64')}`;
  }

  private async get<T>(route: string, params: Record<string, string> = {}): Promise<T> {
    const url = new URL('/index.php', this.origin);
    url.searchParams.set('rest_route', route);
    for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value);
    const response = await fetch(url, { headers: { Authorization: this.auth } });
    if (!response.ok) throw new Error(`WordPress outcome request ${route} returned HTTP ${response.status}`);
    return await response.json() as T;
  }

  async pageContent(pageId?: number): Promise<string> {
    let id = pageId;
    if (!id) {
      const pages = await this.get<Array<{ id: number }>>('/wp/v2/pages', { slug: 'ai-landing-page', status: 'any', context: 'edit' });
      id = pages[0]?.id;
    }
    if (!id) return '';
    const page = await this.get<{ content?: { raw?: string; rendered?: string } }>(`/wp/v2/pages/${id}`, { context: 'edit' });
    return page.content?.raw ?? page.content?.rendered ?? '';
  }

  async product(name = 'AI Tool'): Promise<Product | undefined> {
    const products = await this.get<Product[]>('/wc/v3/products', { search: name, per_page: '100' });
    return products.find((item) => item.name === name);
  }
}
