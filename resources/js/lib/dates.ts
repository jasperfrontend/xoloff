/**
 * Dates are shown the Dutch way, for the same reason money is: this is a Dutch
 * tool used by Dutch people, and a date read as month-first is a date read
 * wrong.
 */
const dateTimeFormatter = new Intl.DateTimeFormat('nl-NL', {
  // Pinned rather than left to the machine. Timestamps are stored in UTC and
  // both people who use xoloff are in the Netherlands, so a server or a
  // laptop set to UTC must not shift every saved-at time by two hours.
  timeZone: 'Europe/Amsterdam',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

const dateFormatter = new Intl.DateTimeFormat('nl-NL', {
  timeZone: 'Europe/Amsterdam',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
});

/**
 * Renders an ISO 8601 timestamp as "20-08-2026 14:32". An absent or
 * unparseable value returns a dash rather than "Invalid Date", because these
 * land in table cells where a tidy column matters more than the excuse.
 */
export function formatDateTime(value: string | null | undefined): string {
  const date = parse(value);

  return date === null ? '-' : dateTimeFormatter.format(date).replace(', ', ' ');
}

/** Renders an ISO 8601 timestamp as "20-08-2026". */
export function formatDate(value: string | null | undefined): string {
  const date = parse(value);

  return date === null ? '-' : dateFormatter.format(date);
}

function parse(value: string | null | undefined): Date | null {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  return Number.isNaN(date.getTime()) ? null : date;
}
