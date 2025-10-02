// Money is stored as int64 kopecks across the backend. Display as
// rubles with two decimals — POS staff want a recognisable amount, not
// a raw kopecks value.
export function formatRub(kopecks: number): string {
  const rub = (kopecks / 100).toFixed(2)
  return `${rub} ₽`
}
