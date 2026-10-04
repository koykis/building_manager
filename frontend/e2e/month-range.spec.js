import { test, expect } from '@playwright/test'

async function dashboard(page, coverage = { first: '2019-04', last: '2026-08', published: 89 }) {
  const requests = []
  await page.clock.setFixedTime(new Date(2026, 9, 2, 12))
  await page.addInitScript(() => localStorage.setItem('locale', 'en'))
  await page.route('**/api/v1/**', route => {
    const url = new URL(route.request().url())
    const totals = { operating: '100.00', capital: '0', reserve: '0', unclassified: '0' }
    const responses = {
      '/api/v1/auth/me': { id: 1, role: 'admin' },
      '/api/v1/reports/coverage': coverage,
      '/api/v1/admin/references': { apartments: [] },
      '/api/v1/reports/my-apartment': [],
    }
    if (url.pathname === '/api/v1/reports/building') {
      requests.push({ from: url.searchParams.get('from'), to: url.searchParams.get('to') })
      return route.fulfill({ json: { categories: [], totals, coverage: { published: 1, expected: 1 }, comparison: null, months: [{ period: url.searchParams.get('from'), available: true, totals, statement_total: '100.00', categories: {} }] } })
    }
    return route.fulfill({ json: responses[url.pathname] })
  })
  await page.goto('/')
  await expect(page.getByRole('button', { name: /Reporting period/ })).toBeEnabled()
  return requests
}

for (const coverage of [
  { first: '2019-04', last: '2026-08', published: 89 },
  { first: '2026-08', last: '2026-08', published: 1 },
  { first: '2025-01', last: '2025-12', published: 12 },
]) {
  test(`defaults to the current calendar YTD with history ending ${coverage.last} and starting ${coverage.first}`, async ({ page }) => {
    const requests = await dashboard(page, coverage)
    expect(requests).toEqual([{ from: '2026-01', to: '2026-10' }])
    const trigger = page.getByRole('button', { name: /Reporting period/ })
    await expect(trigger).toContainText('Jan 2026')
    await expect(trigger).toContainText('Oct 2026')
    await trigger.click()
    const picker = page.getByRole('dialog')
    await expect(picker.getByText('10 months selected', { exact: true })).toBeVisible()
    await expect(picker.getByRole('button', { name: 'January 2026', exact: true })).toBeEnabled()
    await expect(picker.getByRole('button', { name: 'October 2026', exact: true })).toBeEnabled()
    await picker.getByRole('button', { name: 'All history', exact: true }).click()
    await picker.getByRole('button', { name: 'Apply range' }).click()
    await expect.poll(() => requests.at(-1)).toEqual({ from: coverage.first, to: coverage.last })
    await page.reload()
    await expect(trigger).toBeEnabled()
    await expect.poll(() => requests.at(-1)).toEqual({ from: '2026-01', to: '2026-10' })
  })
}

test('cross-year selection previews inclusively and applies only a complete range', async ({ page }) => {
  const errors = []
  page.on('pageerror', error => errors.push(error.message))
  const requests = await dashboard(page)
  const trigger = page.getByRole('button', { name: /Reporting period/ })
  await trigger.click()
  const picker = page.getByRole('dialog', { name: 'Select a month range' })
  await picker.getByRole('combobox', { name: 'Jump to year' }).selectOption('2024')
  await picker.getByRole('button', { name: 'November 2024', exact: true }).click()
  await expect(picker.getByRole('button', { name: 'Apply range' })).toBeDisabled()
  await picker.getByRole('button', { name: 'February 2025', exact: true }).hover()
  await expect(picker.locator('.in-range')).toHaveCount(4)
  await expect(picker.getByRole('button', { name: 'December 2024', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await picker.getByRole('button', { name: 'February 2025', exact: true }).click()
  await expect(picker.getByText('4 months selected', { exact: true })).toBeVisible()
  expect(requests).toHaveLength(1)
  await picker.getByRole('button', { name: 'Apply range' }).click()
  await expect(picker).not.toBeVisible()
  await expect(trigger).toContainText('Nov 2024')
  await expect.poll(() => requests.at(-1)).toEqual({ from: '2024-11', to: '2025-02' })
  await expect(trigger).toBeFocused()

  await trigger.click()
  await picker.getByRole('button', { name: 'Latest 3 months' }).click()
  await picker.getByRole('button', { name: 'Cancel', exact: true }).click()
  expect(requests).toHaveLength(2)
  await expect(trigger).toContainText('Nov 2024')
  expect(errors).toEqual([])
})

test('shortcuts, reversed selection, keyboard cancellation and history boundaries', async ({ page }) => {
  const requests = await dashboard(page)
  const trigger = page.getByRole('button', { name: /Reporting period/ })
  const picker = page.getByRole('dialog')
  await trigger.click()
  await picker.getByRole('button', { name: 'Latest 12 months' }).click()
  await expect(picker.getByText('12 months selected', { exact: true })).toBeVisible()
  await expect(picker.getByRole('button', { name: 'November 2026', exact: true })).toBeDisabled()
  await picker.getByRole('button', { name: 'Apply range' }).click()
  await expect.poll(() => requests.at(-1)).toEqual({ from: '2025-09', to: '2026-08' })
  await expect(trigger).toBeEnabled()
  await trigger.click()
  await picker.getByRole('button', { name: 'February 2026', exact: true }).click()
  await picker.getByRole('button', { name: 'November 2025', exact: true }).click()
  await expect(picker.getByText('4 months selected', { exact: true })).toBeVisible()
  await picker.getByRole('button', { name: 'Apply range' }).click()
  await expect.poll(() => requests.at(-1)).toEqual({ from: '2025-11', to: '2026-02' })
  await expect(trigger).toBeEnabled()
  await trigger.click()
  await picker.getByRole('combobox', { name: 'Jump to year' }).selectOption('2019')
  await expect(picker.getByRole('button', { name: 'March 2019', exact: true })).toBeDisabled()
  await expect(picker.getByRole('button', { name: 'Previous display year' })).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(picker).not.toBeVisible()
  await expect(trigger).toBeFocused()
  expect(requests).toHaveLength(3)
  await trigger.click()
  await picker.getByRole('button', { name: 'All history', exact: true }).click()
  await picker.getByRole('button', { name: 'Apply range' }).click()
  await expect.poll(() => requests.at(-1)).toEqual({ from: '2019-04', to: '2026-08' })
})

test('single-month history works on mobile in Greek and with the keyboard', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  const requests = await dashboard(page, { first: '2026-08', last: '2026-08', published: 1 })
  await page.getByRole('combobox', { name: 'Language / Γλώσσα' }).selectOption('el')
  const trigger = page.getByRole('button', { name: /Περίοδος αναφοράς/ })
  await trigger.click()
  const picker = page.getByRole('dialog', { name: 'Επιλέξτε περίοδο' })
  await expect(picker.locator('.range-year')).toHaveCount(1)
  const month = picker.locator('[data-month="2026-08"]')
  await month.focus()
  await page.keyboard.press('Enter')
  await expect(picker.getByRole('button', { name: 'Εφαρμογή', exact: true })).toBeDisabled()
  await page.keyboard.press('ArrowRight')
  await expect(picker.locator('[data-month="2026-09"]')).toBeFocused()
  await page.keyboard.press('ArrowLeft')
  await expect(month).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(picker.getByText('1 μήνας επιλεγμένος', { exact: true })).toBeVisible()
  const box = await picker.boundingBox()
  expect(box.x).toBeGreaterThanOrEqual(0)
  expect(box.x + box.width).toBeLessThanOrEqual(390)
  await picker.getByRole('button', { name: 'Εφαρμογή', exact: true }).click()
  await expect.poll(() => requests.length).toBe(2)
  expect(requests.at(-1)).toEqual({ from: '2026-08', to: '2026-08' })
  await trigger.click()
  await page.mouse.click(3, 3)
  await expect(picker).not.toBeVisible()
})

test('a failed refresh retains the displayed report and its original range', async ({ page }) => {
  await dashboard(page)
  await page.route('**/api/v1/reports/building?**', route => route.fulfill({ status: 500, json: { message: 'Temporary report error' } }))
  const trigger = page.getByRole('button', { name: /Reporting period/ })
  await trigger.click()
  const picker = page.getByRole('dialog')
  await picker.getByRole('button', { name: 'Latest 3 months' }).click()
  await picker.getByRole('button', { name: 'Apply range' }).click()
  await expect(page.getByText('Temporary report error', { exact: true })).toBeVisible()
  await expect(trigger).toContainText('Jan 2026')
  await expect(trigger).toContainText('Oct 2026')
  await expect(trigger).toBeFocused()
})
