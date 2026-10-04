export function recurringCategories(months, categories) {
  const occurrences = new Map()
  for (const month of months) {
    if (!month.available) continue
    for (const [id, amount] of Object.entries(month.operating_categories || {})) {
      if (Number(amount) !== 0) occurrences.set(id, (occurrences.get(id) || 0) + 1)
    }
  }
  return categories.filter(category => (occurrences.get(String(category.id)) || 0) >= 2)
}

export function monthlyCostStatistics(months, categoryId) {
  const samples = Array.from({ length: 12 }, () => [])
  for (const month of months) {
    if (!month.available || !month.operating_categories) continue
    samples[Number(month.period.slice(5, 7)) - 1].push({
      period: month.period,
      amount: Number(month.operating_categories[categoryId] ?? 0),
      estimated: Boolean(month.estimated),
    })
  }
  return samples.map((entries, index) => {
    const amounts = entries.map(entry => entry.amount)
    return {
      month: index + 1,
      count: entries.length,
      estimatedCount: entries.filter(entry => entry.estimated).length,
      average: entries.length ? amounts.reduce((sum, amount) => sum + amount, 0) / entries.length : null,
      min: entries.length ? Math.min(...amounts) : null,
      max: entries.length ? Math.max(...amounts) : null,
      entries,
    }
  })
}
