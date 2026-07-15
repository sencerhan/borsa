// Yüksek hassasiyetli (string) sayısal değerleri görüntüleme için biçimlendirir.

/** String sayıyı verilen ondalık basamağa yuvarlayıp gruplandırarak biçimlendirir. */
export function formatNumber(
    value: string | number | null | undefined,
    decimals = 8,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const num = typeof value === 'string' ? Number(value) : value;

    if (Number.isNaN(num)) {
        return '—';
    }

    return num.toLocaleString('tr-TR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    });
}

/** Oranı (ör. "0.002") yüzdeye çevirir: "0,20%". */
export function formatPercent(
    rate: string | number | null | undefined,
): string {
    if (rate === null || rate === undefined || rate === '') {
        return '—';
    }

    const num = (typeof rate === 'string' ? Number(rate) : rate) * 100;

    if (Number.isNaN(num)) {
        return '—';
    }

    return `%${num.toLocaleString('tr-TR', { maximumFractionDigits: 4 })}`;
}

/** İki string sayının çarpımını güvenli biçimde döndürür (görüntüleme amaçlı). */
export function multiply(a: string | number, b: string | number): number {
    return Number(a) * Number(b);
}
