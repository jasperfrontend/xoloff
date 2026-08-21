/**
 * Mirrors one entry of App\Support\Text\Placeholders::all(): something that
 * can be dropped into a quote text and becomes customer data when a version is
 * saved.
 */
export interface PlaceholderOption {
  token: string;
  label: string;
  example: string;
}

/** Mirrors App\Enums\QuoteStatus. */
export type QuoteStatus = 'draft' | 'sent' | 'opened' | 'approved' | 'denied';

export type DiscountType = 'percentage' | 'fixed';

export interface QuoteLineItem {
  product_id: number | null;
  name: string;
  specs: Record<string, string> | null;
  quantity: string;
  unit_price_ex_vat: string;
  tax_class_id: number | null;
  discount_type: DiscountType | null;
  discount_value: string | null;
}

export interface QuoteContent {
  discount_type: DiscountType | null;
  discount_value: string | null;
  rounding_override: string | null;
  line_items: QuoteLineItem[];
}

/**
 * Mirrors App\Support\Quotes\CalculatedLine. Every money value is a
 * fixed-precision string, calculated on the server and only ever displayed
 * here.
 */
export interface CalculatedLine {
  lineItemId: number | null;
  name: string;
  quantity: string;
  unitPriceExVat: string;
  subtotal: string;
  lineDiscount: string;
  quoteDiscountShare: string;
  net: string;
  taxClassId: number;
  taxClassName: string;
  taxClassPercentage: string;
}

/** Mirrors App\Support\Quotes\TaxClassTotal. */
export interface TaxClassTotal {
  taxClassId: number;
  name: string;
  percentage: string;
  net: string;
  vat: string;
}

/** Mirrors App\Support\Quotes\CalculatedQuote. */
export interface CalculatedQuote {
  lines: CalculatedLine[];
  taxClassTotals: TaxClassTotal[];
  subtotalBeforeQuoteDiscount: string;
  quoteDiscount: string;
  subtotal: string;
  vatTotal: string;
  calculatedTotal: string;
  roundingOverride: string | null;
  total: string;
}

export interface CustomerOption {
  id: number;
  company_name: string;
}

export interface TaxClassOption {
  id: number;
  name: string;
  percentage: string;
}

export interface ProductOption {
  id: number;
  name: string;
  price_ex_vat: string;
  tax_class_id: number;
  specs: { key: string; value: string }[];
}
