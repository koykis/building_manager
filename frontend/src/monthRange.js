export function monthIndex(period) {
  const [year, month] = period.split('-').map(Number)
  return year * 12 + month - 1
}

export function monthAt(index) {
  return `${Math.floor(index / 12)}-${String(index % 12 + 1).padStart(2, '0')}`
}

export function orderedRange(first, second) {
  return first <= second ? { from: first, to: second } : { from: second, to: first }
}

export function trailingRange(count, first, last) {
  return { from: monthAt(Math.max(monthIndex(first), monthIndex(last) - count + 1)), to: last }
}

export function monthCount(from, to) {
  return monthIndex(to) - monthIndex(from) + 1
}

export function yearToDate(date = new Date()) {
  const year = date.getFullYear()
  return { from: `${year}-01`, to: `${year}-${String(date.getMonth() + 1).padStart(2, '0')}` }
}
