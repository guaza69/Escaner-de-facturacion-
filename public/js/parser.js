function parseQR(texto) {
    const data = {};

    // Lista de todas las etiquetas conocidas del QR DIAN para usar en lookaheads
    const tags = 'NumFac|FecFac|HorFac|ValTolFac|CUFE|NitFac|DocAdq|NomAdq';

    const regexId    = new RegExp(`NumFac\\s*:\\s*(.*?)(?=${tags}|$)`, 'si');
    const regexFecha = new RegExp(`FecFac\\s*:\\s*(.*?)(?=${tags}|$)`, 'si');
    const regexValor = new RegExp(`ValTolFac\\s*:\\s*(.*?)(?=${tags}|$)`, 'si');
    const regexCufe  = new RegExp(`CUFE\\s*:\\s*(.*?)(?=${tags}|$)`, 'si');

    const matchId    = texto.match(regexId);
    const matchFecha = texto.match(regexFecha);
    const matchValor = texto.match(regexValor);
    const matchCufe  = texto.match(regexCufe);

    if (matchId)    data.id    = matchId[1].trim();
    if (matchFecha) data.fecha = matchFecha[1].trim().split('T')[0]; // Asegura formato YYYY-MM-DD
    if (matchValor) data.valor = matchValor[1].trim();
    if (matchCufe)  data.cufe  = matchCufe[1].trim();

    return data;
}