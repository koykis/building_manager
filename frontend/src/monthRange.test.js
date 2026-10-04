import { describe, expect, it } from 'vitest'
import { monthAt, monthCount, monthIndex, orderedRange, trailingRange, yearToDate } from './monthRange'

describe('Month ranges', () => {
  it('counts both endpoints across years and leap-year February', () => {
    expect(monthCount('2024-11', '2025-02')).toBe(4)
    expect(monthCount('2024-02', '2024-02')).toBe(1)
    expect(monthAt(monthIndex('2024-12') + 1)).toBe('2025-01')
  })

  it('accepts selection in either direction and a single month', () => {
    expect(orderedRange('2025-02', '2024-11')).toEqual({ from: '2024-11', to: '2025-02' })
    expect(orderedRange('2025-02', '2025-02')).toEqual({ from: '2025-02', to: '2025-02' })
  })

  it('anchors shortcuts to the last published month and clamps to available history', () => {
    expect(trailingRange(12, '2019-04', '2026-08')).toEqual({ from: '2025-09', to: '2026-08' })
    expect(trailingRange(3, '2026-07', '2026-08')).toEqual({ from: '2026-07', to: '2026-08' })
  })

  it('defaults to January through the current local calendar month', () => {
    expect(yearToDate(new Date(2026, 9, 2))).toEqual({ from: '2026-01', to: '2026-10' })
    expect(yearToDate(new Date(2026, 11, 31, 23, 59))).toEqual({ from: '2026-01', to: '2026-12' })
    expect(yearToDate(new Date(2027, 0, 1))).toEqual({ from: '2027-01', to: '2027-01' })
  })
})
