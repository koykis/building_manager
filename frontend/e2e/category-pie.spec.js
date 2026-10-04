import { test, expect } from '@playwright/test'

test('pie sorting preserves category colors and hidden selections', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))
  const categories = ['Large', 'Small', 'Medium', 'Tied', 'Zero'].map((name, index) => ({ id: index + 1, name_el: name, name_en: name }))
  const totals = { operating: '949.00', capital: '0', reserve: '0', unclassified: '0' }
  const responses = {
    '/api/v1/auth/me': { id: 1, role: 'admin' },
    '/api/v1/reports/coverage': { first: '2026-08', last: '2026-08' },
    '/api/v1/reports/recurring': { categories: [], statistics: {}, coverage: { published: 0, expected: 0, estimated: 0 } },
    '/api/v1/reports/building': {
      categories, totals, coverage: { published: 1, expected: 1 }, comparison: null,
      months: [{ period: '2026-08', available: true, totals, statement_total: '949.00', categories: { 1: '900.00', 2: '9.00', 3: '20.00', 4: '20.00', 5: '0.00' } }],
    },
    '/api/v1/admin/references': { apartments: [] },
    '/api/v1/reports/my-apartment': [],
  }
  await page.route('**/api/v1/**', route => {
    const path = new URL(route.request().url()).pathname
    return route.fulfill({ json: responses[path] })
  })
  await page.goto('/')
  const pie = page.locator('.category-pie')
  const names = pie.locator('.category-name')
  const sort = pie.getByRole('button', { name: 'Ταξινόμηση κατά ποσό', exact: true })
  await expect(names).toHaveText(['Large', 'Medium', 'Tied', 'Small', 'Zero'])
  await expect(sort).toHaveAttribute('aria-pressed', 'false')
  await expect(sort).toContainText('Φθίνουσα')

  const medium = pie.getByRole('button', { name: /Medium/ })
  const swatch = medium.locator('.category-swatch')
  const originalColor = await swatch.evaluate(element => element.style.backgroundColor)
  await medium.click()
  await expect(medium).toHaveAttribute('aria-pressed', 'false')
  await sort.click()
  await expect(names).toHaveText(['Zero', 'Small', 'Medium', 'Tied', 'Large'])
  await expect(sort).toHaveAttribute('aria-pressed', 'true')
  await expect(sort).toContainText('Αύξουσα')
  await expect(medium).toHaveAttribute('aria-pressed', 'false')
  await expect(swatch).toHaveCSS('background-color', originalColor)
  await expect(medium.locator('.category-amount')).toContainText('20')

  await page.getByRole('combobox', { name: 'Language / Γλώσσα' }).selectOption('en')
  const englishSort = pie.getByRole('button', { name: 'Sort by amount', exact: true })
  await expect(englishSort).toContainText('Ascending')
  await englishSort.click()
  await expect(names).toHaveText(['Large', 'Medium', 'Tied', 'Small', 'Zero'])
  await expect(englishSort).toContainText('Descending')
  await expect(medium).toHaveAttribute('aria-pressed', 'false')
  await medium.click()
  await expect(medium).toHaveAttribute('aria-pressed', 'true')

  await page.setViewportSize({ width: 390, height: 844 })
  await englishSort.click()
  await expect(names).toHaveText(['Zero', 'Small', 'Medium', 'Tied', 'Large'])
  await expect(englishSort).toBeVisible()
  expect(errors).toEqual([])
})
