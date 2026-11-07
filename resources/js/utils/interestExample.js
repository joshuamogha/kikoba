/**
 * Plain-words example of what a rate costs: 10,000 borrowed for 6 months, for each way a rate can be charged.
 */
export const interestPeriods = [
    { value: "per_loan", label: "Once for the whole loan (fixed)" },
    { value: "per_month", label: "Every month" },
    { value: "per_year", label: "Per year" },
];

export function interestExample(period, rate) {
    const principal = 10000;
    const months = 6;
    const percent = Number(rate) || 0;
    const interest = principal * (percent / 100) * ({ per_loan: 1, per_month: months, per_year: months / 12 }[period] ?? 1);
    const format = (value) => Math.round(value).toLocaleString("en-US");
    const how = {
        per_loan: `${percent}% of ${format(principal)} once, however long the loan runs`,
        per_month: `${percent}% of ${format(principal)} for each of the ${months} months`,
        per_year: `${percent}% a year, which is ${percent / 2}% of ${format(principal)} for ${months} months`,
    }[period] ?? "";

    return `Example: borrow ${format(principal)} for ${months} months at ${percent}%. Interest is ${how}, so you pay ${format(principal)} + ${format(interest)} = ${format(principal + interest)}.`;
}
