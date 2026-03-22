/**
 * Formato DD/MM/AAAA para fechas YYYY-MM-DD o ISO.
 */
export function formatDateDMY(value) {
    if (value == null || value === '') {
        return '';
    }
    const s = String(value).slice(0, 10);
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    if (m) {
        return `${m[3]}/${m[2]}/${m[1]}`;
    }
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) {
        return String(value);
    }
    return dt.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/** Clave YYYY-MM → MM/AAAA */
export function formatMonthYearLabel(ym) {
    if (!ym || typeof ym !== 'string') {
        return ym;
    }
    const parts = ym.split('-');
    if (parts.length === 2) {
        return `${parts[1]}/${parts[0]}`;
    }
    return ym;
}
