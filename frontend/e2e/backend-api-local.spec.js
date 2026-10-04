import { test, expect } from '@playwright/test'
import fs from 'node:fs'

test('local optimized API formats agree and paginated history stays accessible', async ({ page }) => {
  const credentials = JSON.parse(fs.readFileSync(new URL('../../.local/admin-credentials.json', import.meta.url), 'utf8'))
  await page.goto('/')
  await page.getByLabel('Email', { exact: true }).fill(credentials.email)
  await page.getByLabel('Κωδικός πρόσβασης', { exact: true }).fill(credentials.password)
  await page.getByRole('button', { name: 'Σύνδεση', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Επισκόπηση', exact: true })).toBeVisible()
  const coverage = await (await page.request.get('/api/v1/reports/coverage')).json()
  const range = `from=${coverage.first}&to=${coverage.last}`
  const fullResponse = await page.request.get(`/api/v1/reports/building?${range}`)
  const full = await fullResponse.json()
  const summaryResponse = await page.request.get(`/api/v1/reports/building?${range}&view=summary`)
  const summary = await summaryResponse.json()
  expect(summary.totals).toEqual(full.totals)
  expect(summary.coverage).toEqual(full.coverage)
  const seriesResponse = await page.request.get(`/api/v1/reports/building?${range}&view=series`)
  const series = await seriesResponse.json()
  expect(series.labels).toEqual(full.months.map(month => month.period))
  expect(series.datasets[0].data).toEqual(full.months.map(month => month.available ? month.totals.operating : null))
  const recurringResponse = await page.request.get(`/api/v1/reports/recurring?${range}`)
  expect(recurringResponse.ok()).toBeTruthy()
  expect(recurringResponse.headers()['cache-control']).toContain('private')
  const recurring = await recurringResponse.json()
  expect(recurring.coverage.published).toBe(full.coverage.published)
  const csvResponse = await page.request.get(`/api/v1/reports/building?${range}&format=csv`)
  expect(csvResponse.headers()['content-type']).toContain('text/csv')
  expect((await csvResponse.text()).trim().split('\n').length).toBe(full.months.length + 1)
  const metrics = {
    publishedMonths: full.coverage.published,
    fullReportBytes: (await fullResponse.body()).length,
    summaryBytes: (await summaryResponse.body()).length,
    seriesBytes: (await seriesResponse.body()).length,
    recurringBytes: (await recurringResponse.body()).length,
  }
  console.log('Local report response sizes:', JSON.stringify(metrics))
  fs.writeFileSync(new URL('../../.local/backend-api-measurements.json', import.meta.url), JSON.stringify(metrics, null, 2))
  expect(metrics.recurringBytes).toBeLessThan(metrics.fullReportBytes)
  expect(metrics.summaryBytes).toBeLessThan(metrics.fullReportBytes)
  await page.getByRole('button', { name: 'Μηνιαίες καταστάσεις', exact: true }).click()
  await expect(page.locator('tbody tr')).toHaveCount(24)
  const firstMonth = await page.locator('tbody tr').first().locator('td').first().innerText()
  await page.getByRole('button', { name: 'Επόμενη σελίδα', exact: true }).first().click()
  await expect(page.getByText('Σελίδα 2', { exact: true })).toBeVisible()
  await expect(page.locator('tbody tr').first().locator('td').first()).not.toHaveText(firstMonth)
  await page.getByRole('button', { name: 'Προηγούμενη σελίδα', exact: true }).first().click()
  await expect(page.locator('tbody tr').first().locator('td').first()).toHaveText(firstMonth)
})
