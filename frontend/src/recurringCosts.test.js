import { describe, expect, it } from 'vitest'
import { monthlyCostStatistics, recurringCategories } from './recurringCosts'

const month = (period, costs, extra = {}) => ({ period, available: true, operating_categories: costs, ...extra })

describe('Recurring monthly costs', () => {
  it('compares monthly totals for the same calendar month across years', () => {
    const result = monthlyCostStatistics([
      month('2024-01', { 1: '100.25' }),
      month('2025-01', { 1: '200.75' }, { estimated: true }),
      month('2025-02', { 1: '60' }),
    ], '1')
    expect(result[0]).toMatchObject({ month: 1, count: 2, estimatedCount: 1, average: 150.5, min: 100.25, max: 200.75 })
    expect(result[1]).toMatchObject({ count: 1, average: 60, min: 60, max: 60 })
    expect(result[2]).toMatchObject({ count: 0, average: null, min: null, max: null })
    expect(result).toHaveLength(12)
  })

  it('counts uncharged published months as zero and excludes missing statements', () => {
    const result = monthlyCostStatistics([
      month('2023-01', { 1: '90' }),
      month('2024-01', {}),
      month('2025-01', { 1: '0.0000' }),
      { period: '2026-01', available: false, operating_categories: null },
    ], '1')
    expect(result[0]).toMatchObject({ count: 3, average: 30, min: 0, max: 90 })
  })

  it('offers only categories charged in at least two published operating months', () => {
    const categories = [1, 2, 3, 4].map(id => ({ id }))
    expect(recurringCategories([
      month('2024-01', { 1: '10', 2: '0', 3: '20' }),
      month('2024-02', { 1: '20', 2: '0' }, { categories: { 4: '100' } }),
      month('2024-03', { 1: '30' }, { available: false }),
    ], categories)).toEqual([{ id: 1 }])
  })
})
