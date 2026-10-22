import assert from 'node:assert/strict';
import test from 'node:test';
import { menuContainsPage } from './menu-outcome.js';

test('menu outcome requires the page ID and post_type link in the requested menu', () => {
  const items = [
    { object_id: 42, object: 'page', type: 'post_type', menus: 7 },
    { object_id: 42, object: 'page', type: 'post_type', menus: 8 },
  ];
  assert.equal(menuContainsPage(items, 7, 42), true);
  assert.equal(menuContainsPage(items, 9, 42), false);
  assert.equal(menuContainsPage([{ object_id: 42, object: 'page', type: 'custom', menus: 7 }], 7, 42), false);
});
