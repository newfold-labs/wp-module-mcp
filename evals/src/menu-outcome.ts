interface MenuItem {
  object_id?: number;
  object?: string;
  type?: string;
  menus?: number | number[];
}

export function menuContainsPage(items: MenuItem[], menuId: number, pageId: number): boolean {
  return items.some((item) =>
    item.object_id === pageId && item.object === 'page' && item.type === 'post_type' &&
    (item.menus === menuId || Array.isArray(item.menus) && item.menus.includes(menuId))
  );
}

export class MenuOutcomeFixture {
  readonly pageId: number;
  readonly menuId: number;
  private readonly baseUrl: string;
  private readonly authorization: string;

  private constructor(baseUrl: string, authorization: string, pageId: number, menuId: number) {
    this.baseUrl = baseUrl;
    this.authorization = authorization;
    this.pageId = pageId;
    this.menuId = menuId;
  }

  static async create(serverUrl: string, user: string, password: string): Promise<MenuOutcomeFixture> {
    const baseUrl = new URL(serverUrl).origin;
    const authorization = `Basic ${Buffer.from(`${user}:${password}`).toString('base64')}`;
    const request = (route: string, method: string, data?: unknown) => MenuOutcomeFixture.request(baseUrl, authorization, route, method, data);
    const page = await request('/wp/v2/pages', 'POST', { title: 'AI Eval Landing Page', status: 'publish' }) as { id: number };
    try {
      const menu = await request('/wp/v2/menus', 'POST', { name: `AI Eval Primary ${page.id}` }) as { id: number };
      return new MenuOutcomeFixture(baseUrl, authorization, page.id, menu.id);
    } catch (error) {
      await request(`/wp/v2/pages/${page.id}`, 'DELETE', { force: true });
      throw error;
    }
  }

  private static async request(baseUrl: string, authorization: string, route: string, method: string, data?: unknown): Promise<unknown> {
    const url = new URL('/index.php', baseUrl);
    url.searchParams.set('rest_route', route);
    if (method === 'GET' && data && typeof data === 'object') {
      for (const [key, value] of Object.entries(data)) url.searchParams.set(key, String(value));
    }
    const response = await fetch(url, {
      method,
      headers: { Authorization: authorization, 'Content-Type': 'application/json' },
      body: method === 'GET' ? undefined : JSON.stringify(data ?? {}),
    });
    const payload = await response.json() as unknown;
    if (!response.ok) throw new Error(`WordPress REST ${route} returned HTTP ${response.status}`);
    return payload;
  }

  private request(route: string, method: string, data?: unknown): Promise<unknown> {
    return MenuOutcomeFixture.request(this.baseUrl, this.authorization, route, method, data);
  }

  async containsPage(): Promise<boolean> {
    const items = await this.request('/wp/v2/menu-items', 'GET', { menus: this.menuId, context: 'edit', per_page: 100 }) as MenuItem[];
    return menuContainsPage(items, this.menuId, this.pageId);
  }

  async ensurePageItem(): Promise<void> {
    if (await this.containsPage()) return;
    await this.request('/wp/v2/menu-items', 'POST', {
      title: 'AI Eval Landing Page', type: 'post_type', object: 'page', object_id: this.pageId,
      menus: this.menuId, status: 'publish',
    });
  }

  async cleanup(): Promise<void> {
    await this.request(`/wp/v2/menus/${this.menuId}`, 'DELETE', { force: true });
    await this.request(`/wp/v2/pages/${this.pageId}`, 'DELETE', { force: true });
  }
}
