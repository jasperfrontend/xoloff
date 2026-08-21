import { describe, expect, it } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/dates';

describe('formatDateTime', () => {
  /**
   * Written in UTC on purpose: these are the timestamps the server sends, and
   * the point is that they land in Dutch local time whatever the machine
   * running this is set to.
   */
  it('shows a utc timestamp in dutch local time', () => {
    expect(formatDateTime('2026-08-12T12:32:00Z')).toBe('12-08-2026 14:32');
    // Winter, so one hour ahead rather than two.
    expect(formatDateTime('2026-01-05T08:07:00Z')).toBe('05-01-2026 09:07');
  });

  it('reads day first, the way a date is read here', () => {
    // The eighth of the month, not August the twelfth.
    expect(formatDateTime('2026-08-12T14:32:00+02:00')).toBe('12-08-2026 14:32');
  });

  it('uses the twenty-four hour clock', () => {
    expect(formatDateTime('2026-08-12T21:05:00+02:00')).toBe('12-08-2026 21:05');
  });

  it('pads a single digit day and month', () => {
    expect(formatDateTime('2026-01-05T09:07:00+01:00')).toBe('05-01-2026 09:07');
  });

  /**
   * These land in table cells. "Invalid Date" would be wider than every real
   * value in the column and says nothing useful.
   */
  it('shows a dash rather than an excuse when there is nothing to show', () => {
    expect(formatDateTime(null)).toBe('-');
    expect(formatDateTime(undefined)).toBe('-');
    expect(formatDateTime('')).toBe('-');
    expect(formatDateTime('not a date')).toBe('-');
  });
});

describe('formatDate', () => {
  it('leaves the time off', () => {
    expect(formatDate('2026-08-12T14:32:00+02:00')).toBe('12-08-2026');
  });

  it('shows a dash rather than an excuse when there is nothing to show', () => {
    expect(formatDate(null)).toBe('-');
    expect(formatDate('not a date')).toBe('-');
  });
});
