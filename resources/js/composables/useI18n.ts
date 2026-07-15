import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Dict = Record<string, unknown>;

/**
 * Basit i18n: sunucudan paylaşılan `translations` sözlüğünden nokta ile
 * ayrılmış anahtarları çözer ve `:param` yer tutucularını değiştirir.
 * Aktif dil tarayıcı diline göre sunucuda seçilir.
 */
export function useI18n() {
    const page = usePage();

    const locale = computed(() => page.props.locale);

    function t(key: string, params?: Record<string, string | number>): string {
        const dict = page.props.translations as Dict;
        const value = key.split('.').reduce<unknown>((obj, part) => {
            if (obj && typeof obj === 'object') {
                return (obj as Dict)[part];
            }

            return undefined;
        }, dict);

        if (typeof value !== 'string') {
            return key; // anahtar bulunamazsa anahtarı döndür (geliştirme yardımı)
        }

        if (!params) {
            return value;
        }

        return Object.entries(params).reduce(
            (str, [k, v]) => str.replaceAll(`:${k}`, String(v)),
            value,
        );
    }

    return { t, locale };
}
