/**
 * Suite de Pruebas para el Parser de QR
 */

function ejecutarPruebasParser() {
    console.log("🧪 Iniciando pruebas unitarias del parser...");

    const casos = [
        {
            nombre: "Orden diferente y espacios extra",
            entrada: `
                ValTolFac :   50000.00   
                CUFE:hash123
                NumFac:FESM1
                FecFac: 2026-04-27
            `,
            esperado: { id: "FESM1", valor: "50000.00", fecha: "2026-04-27", cufe: "hash123" }
        },
        {
            nombre: "Líneas irrelevantes y campos incompletos",
            entrada: `
                --- INICIO QR ---
                NumFac: FESM2
                Texto basura: algo que no sirve
                ValTolFac: 100.50
                --- FIN QR ---
            `,
            esperado: { id: "FESM2", valor: "100.50" }
        },
        {
            nombre: "Formato con múltiples espacios y tabs",
            entrada: `NumFac\t:\tFESM3\nFecFac   :   2026-01-01`,
            esperado: { id: "FESM3", fecha: "2026-01-01" }
        },
        {
            nombre: "Factura en UNA SOLA LÍNEA (Bug Reportado)",
            entrada: "NumFac : FESM519003FecFac: 2026-04-26ValTolFac: 416748CUFE: MC41NDgz",
            esperado: { id: "FESM519003", fecha: "2026-04-26", valor: "416748", cufe: "MC41NDgz" }
        }
    ];

    let pasaron = 0;

    casos.forEach(caso => {
        const resultado = parseQR(caso.entrada);
        const exito = JSON.stringify(resultado) === JSON.stringify(caso.esperado);
        
        if (exito) {
            console.log(`✅ PASÓ: ${caso.nombre}`);
            pasaron++;
        } else {
            console.error(`❌ FALLÓ: ${caso.nombre}`);
            console.log("   Entrada:", caso.entrada);
            console.log("   Esperado:", caso.esperado);
            console.log("   Obtenido:", resultado);
        }
    });

    console.log(`\n📊 Resultado: ${pasaron}/${casos.length} pruebas exitosas.`);
    return { total: casos.length, exitos: pasaron };
}
