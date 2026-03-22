/**
 * Etiqueta visible de tarjeta: "Nombre · •••• 1234" cuando hay últimos 4 dígitos.
 * @param {{ name?: string, last_4_digits?: string|null }} card
 */
export function formatCardLabel(card) {
    if (!card) {
        return '';
    }
    const name = card.name ?? '';
    const d = card.last_4_digits;
    if (d == null || String(d).trim() === '') {
        return name;
    }
    return `${name} · •••• ${d}`;
}
