import { test, expect } from '@playwright/test'
import { monthlyCostStatistics, recurringCategories } from '../src/recurringCosts'

async function dashboard(page, { failHistory = false, empty = false } = {}) {
  await page.clock.setFixedTime(new Date(2026, 9, 4, 12))
  await page.addInitScript(() => localStorage.setItem('locale', 'en'))
  const totals = { operating: '100', capital: '0', reserve: '0', unclassified: '0' }
  const categories = [
    { id: 1, name_en: 'Cleaning', name_el: 'Καθαρισμός' },
    { id: 2, name_en: 'Water', name_el: 'Νερό' },
    { id: 3, name_en: 'One-off work', name_el: 'Έκτακτη εργασία' },
    { id: 4, name_en: 'Capital repairs', name_el: 'Κεφαλαιουχικές επισκευές' },
  ]
  const month = (period, costs, extra = {}) => ({ period, available: true, operating_categories: costs, categories: costs, totals, statement_total: '100', ...extra })
  const history = [
    month('2024-01', { 1: '100', 2: '30' }, { categories: { 1: '50100', 2: '30', 4: '400' } }),
    month('2024-02', { 1: '80', 2: '50' }),
    month('2025-01', { 1: '200', 2: '60' }, { estimated: true }),
    month('2025-02', { 1: '120' }),
    month('2026-01', {}),
    { period: '2026-02', available: false, operating_categories: null, categories: null, totals: null },
    month('2026-03', { 3: '300' }),
  ]
  const responses = {
    '/api/v1/auth/me': { id: 1, role: 'admin' },
    '/api/v1/reports/coverage': { first: '2024-01', last: '2026-03', published: 6 },
    '/api/v1/admin/references': { apartments: [] },
    '/api/v1/reports/my-apartment': [],
  }
  await page.route('**/api/v1/**', route => {
    const url = new URL(route.request().url())
    if (url.pathname === '/api/v1/reports/recurring') {
      if (failHistory) {
        failHistory = false
        return route.fulfill({ status: 500, json: { message: 'History unavailable' } })
      }
      const recurring = empty ? [] : recurringCategories(history, categories)
      const statistics = Object.fromEntries(recurring.map(category => {
        const months = monthlyCostStatistics(history, String(category.id))
        const entries = months.flatMap(month => month.entries)
        const amounts = entries.map(entry => entry.amount)
        return [category.id, { months: months.map(({ entries, estimatedCount, ...month }) => ({ ...month, estimated_count: estimatedCount })), summary: { average: amounts.reduce((sum, amount) => sum + amount, 0) / amounts.length, min: Math.min(...amounts), max: Math.max(...amounts) } }]
      }))
      return route.fulfill({ json: { from: '2024-01', to: '2026-03', categories: recurring, statistics, coverage: { published: 6, expected: 7, estimated: 1 } } })
    }
    if (url.pathname === '/api/v1/reports/building') {
      const historical = url.searchParams.get('from') === '2024-01'
      if (historical && failHistory) {
        failHistory = false
        return route.fulfill({ status: 500, json: { message: 'History unavailable' } })
      }
      const months = empty ? [] : historical ? history : history.slice(4)
      return route.fulfill({ json: { categories, months, totals, coverage: { published: 6, expected: 7 }, comparison: null } })
    }
    return route.fulfill({ json: responses[url.pathname] })
  })
  await page.goto('/')
  return page.locator('.recurring-costs')
}

test('category and period selectors update the range chart and exact figures', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))
  const widget = await dashboard(page)
  const selector = widget.getByLabel('Recurring expense', { exact: true })
  await expect(selector.locator('option')).toHaveText(['Cleaning', 'Water'])
  await expect(widget.getByRole('img')).toBeVisible()
  await widget.getByText('Monthly figures', { exact: true }).click()
  const january = widget.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'January', exact: true }) })
  await expect(january).toContainText('€100.00')
  await expect(january).toContainText('€0.00')
  await expect(january).toContainText('€200.00')
  await expect(january).toContainText('Estimated months: 1')
  const december = widget.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'December', exact: true }) })
  await expect(december).toContainText('Unavailable')
  await selector.selectOption('2')
  await expect(january).toContainText('€30.00')
  await expect(january).toContainText('€60.00')
  await widget.getByLabel('Statistics period').selectOption('selected')
  await expect(january).toContainText('€0.00')
  await expect(widget.locator('.recurring-summary dd')).toHaveText(['€0.00', '€0.00', '€0.00'])
  await expect(widget.getByText('Unavailable months excluded: 1', { exact: false })).toBeVisible()
  await widget.getByLabel('Statistics period').selectOption('history')
  await expect(january).toContainText('€30.00')
  expect(errors).toEqual([])
})

test('Greek mobile view fits the screen and keeps the figures accessible', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  const widget = await dashboard(page)
  await expect(widget.getByLabel('Recurring expense', { exact: true })).toBeVisible()
  await page.getByRole('combobox', { name: 'Language / Γλώσσα' }).selectOption('el')
  await expect(widget.getByRole('heading', { name: 'Επαναλαμβανόμενα έξοδα' })).toBeVisible()
  await expect(widget.getByLabel('Επαναλαμβανόμενη δαπάνη').locator('option')).toHaveText(['Καθαρισμός', 'Νερό'])
  await widget.getByText('Μηνιαία ποσά', { exact: true }).click()
  await expect(widget.getByRole('rowheader', { name: 'Ιανουαρίου', exact: true })).toBeVisible()
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390)
})

test('history loading can be retried and empty reports explain the lack of recurring data', async ({ page }) => {
  const widget = await dashboard(page, { failHistory: true })
  await expect(widget.getByRole('alert')).toContainText('History unavailable')
  await widget.getByRole('button', { name: 'Refresh' }).click()
  await expect(widget.getByLabel('Recurring expense', { exact: true })).toBeVisible()
  await dashboard(page, { empty: true })
  await expect(widget.getByText('No operating expenses recorded in at least two published months yet.')).toBeVisible()
  await expect(widget.getByRole('img')).toHaveCount(0)
})
