/**
 * Simulador de Escaneo QR para FactuFlow
 */

// ─── Función auxiliar: crear panel de logs en pantalla ──────────
function crearPanel(color = '#0f0', borderColor = '#333') {
    const container = document.querySelector('.container');
    // Eliminar panel anterior si existe
    const viejo = document.getElementById('simPanel');
    if (viejo) viejo.remove();

    const panel = document.createElement('div');
    panel.id = 'simPanel';
    panel.style.cssText = `background:#000; color:${color}; font-family:monospace; padding:14px;
        margin-top:20px; height:220px; overflow-y:auto; font-size:12px;
        border-radius:12px; border:1px solid ${borderColor};`;
    container.appendChild(panel);

    return (msg, c = color) => {
        const d = document.createElement('div');
        d.style.color = c;
        d.innerHTML = `<span style="opacity:0.4">[${new Date().toLocaleTimeString()}]</span> ${msg}`;
        panel.appendChild(d);
        panel.scrollTop = panel.scrollHeight;
    };
}

// ─── Simulación normal (100 escaneos con IDs únicos) ────────────
async function simularEscaneos(cantidad = 100) {
    const input = document.getElementById('scanner');
    if (!input) { console.error("Input #scanner no encontrado"); return; }

    const log = crearPanel('#86efac', '#22c55e');

    // Offset basado en timestamp → IDs únicos en cada corrida
    const base = Math.floor(Date.now() / 1000) % 1000000;
    log(`🚀 Iniciando ${cantidad} escaneos. Base: SIM${base}0001`);

    let exitos = 0, errores = 0, duplicados = 0;

    for (let i = 1; i <= cantidad; i++) {
        const id    = `SIM${base}${String(i).padStart(4,'0')}`;
        const valor = Math.floor(Math.random() * (500000 - 10000 + 1)) + 10000;
        const hash  = Math.random().toString(36).slice(2, 18);

        input.value = `NumFac : ${id}\nFecFac: 2026-04-26\nHorFac: 19:59:23-05:00\nValTolFac: ${valor}\nCUFE: ${hash}`;
        input.dispatchEvent(new Event('change', { bubbles: true }));

        await new Promise(r => setTimeout(r, Math.floor(Math.random() * 1000) + 1000));
    }

    log(`✅ Completado. Revisa la tabla y consola (F12) para resultados.`, '#fff');
}

// ─── Prueba de estrés (500 escaneos rápidos con IDs únicos) ─────
async function pruebaDeEstres(cantidad = 500) {
    const input = document.getElementById('scanner');
    const log = crearPanel('#86efac', '#22c55e');

    const base = Math.floor(Date.now() / 1000) % 1000000;
    log(`🔥 PRUEBA DE ESTRÉS: ${cantidad} registros. Base: STR${base}0001`);

    let enviados = 0;

    for (let i = 1; i <= cantidad; i++) {
        const id    = `STR${base}${String(i).padStart(4,'0')}`;
        const valor = Math.floor(Math.random() * (500000 - 50000 + 1)) + 50000;
        const hash  = btoa(Math.random().toString()).slice(0, 32);

        input.value = `NumFac : ${id}\nFecFac: 2026-04-26\nValTolFac: ${valor}\nCUFE: ${hash}`;
        input.dispatchEvent(new Event('change', { bubbles: true }));

        log(`[${i}/${cantidad}] Enviando ${id}...`);

        const delay = Math.floor(Math.random() * (500 - 200 + 1)) + 200;
        await new Promise(r => setTimeout(r, delay));
        enviados++;
    }

    log(`✅ ESTRÉS FINALIZADO. ${enviados} enviados.`, '#fff');
}

async function pruebaErrores() {
    const input = document.getElementById('scanner');
    const container = document.querySelector('.container');
    
    const logPanel = document.createElement('div');
    logPanel.style.cssText = 'background:#1a1a1a; color:#f87171; font-family:monospace; padding:10px; margin-top:20px; height:250px; overflow-y:auto; font-size:12px; border-radius:10px; border:1px solid #ef4444;';
    container.appendChild(logPanel);

    const log = (msg, color = "#f87171") => {
        const div = document.createElement('div');
        div.style.color = color;
        div.innerText = `[LOG] ${msg}`;
        logPanel.appendChild(div);
        logPanel.scrollTop = logPanel.scrollHeight;
    };

    log("⚠️ INICIANDO PRUEBA DE MANEJO DE ERRORES", "#fbbf24");

    const casos = [
        { 
            nombre: "Normal 1", 
            txt: "NumFac : FESM519009\nValTolFac: 1000\nCUFE: C1" 
        },
        { 
            nombre: "Falta de consecutivo (Saltamos el 010)", 
            txt: "NumFac : FESM519011\nValTolFac: 2000\nCUFE: C2" 
        },
        { 
            nombre: "Factura duplicada (Primera vez)", 
            txt: "NumFac : FESM519015\nValTolFac: 3000\nCUFE: C3" 
        },
        { 
            nombre: "Factura duplicada (Repetida)", 
            txt: "NumFac : FESM519015\nValTolFac: 3000\nCUFE: C3" 
        },
        { 
            nombre: "QR Incompleto (Sin CUFE)", 
            txt: "NumFac : FESM519016\nValTolFac: 4000" 
        },
        { 
            nombre: "Texto Inválido", 
            txt: "Esto es solo un mensaje de texto que no es un QR" 
        }
    ];

    for (const caso of casos) {
        log(`Ejecutando: ${caso.nombre}...`, "#fff");
        input.value = caso.txt;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(r => setTimeout(r, 1000));
    }

    log("🔍 Consultando validación de consecutivos en el servidor...");
    try {
        const res = await fetch('../api/validar_consecutivos.php');
        const faltantes = await res.json();
        log("Faltantes detectados por el servidor: " + JSON.stringify(faltantes), "#6ee7b7");
    } catch(e) {
        log("Error al consultar validación: " + e.message);
    }

    log("🔚 PRUEBA DE ERRORES FINALIZADA", "#fbbf24");
}

async function simularComportamientoReal(iteraciones = 20) {
    const input = document.getElementById('scanner');
    const container = document.querySelector('.container');
    
    const logPanel = document.createElement('div');
    logPanel.style.cssText = 'background:#111; color:#a5f3fc; font-family:monospace; padding:15px; margin-top:20px; height:300px; overflow-y:auto; font-size:13px; border-radius:12px; border:1px solid #22d3ee; box-shadow: 0 0 15px rgba(34, 211, 238, 0.2);';
    container.appendChild(logPanel);

    const log = (msg, color = "#a5f3fc") => {
        const div = document.createElement('div');
        div.style.padding = "2px 0";
        div.style.color = color;
        div.innerHTML = `<span style="opacity:0.5">[${new Date().toLocaleTimeString()}]</span> ${msg}`;
        logPanel.appendChild(div);
        logPanel.scrollTop = logPanel.scrollHeight;
    };

    log("👤 INICIANDO SIMULACIÓN DE COMPORTAMIENTO HUMANO", "#22d3ee");
    log("Escenerio: Escaneos con pausas, errores ocasionales y duplicados.", "#94a3b8");

    let ultimaFactura = null;

    for (let i = 1; i <= iteraciones; i++) {
        const azar = Math.random();
        let txt = "";
        let nombre = "";

        if (azar < 0.1) { // 10% probabilidad de QR inválido
            nombre = "❌ ERROR: QR Inválido";
            txt = "TICKET NO FISCAL - CONSUMO INTERNO";
        } else if (azar < 0.25 && ultimaFactura) { // 15% probabilidad de duplicado
            nombre = "🔄 DUPLICADO: Re-escaneando anterior";
            txt = ultimaFactura;
        } else { // 75% probabilidad de factura normal
            const id = 7000 + i;
            nombre = `📄 NORMAL: Factura FESM${id}`;
            txt = `NumFac : FESM${id}\nValTolFac: ${100 * i}\nCUFE: HASH_HUMAN_${i}`;
            ultimaFactura = txt;
        }

        log(`Acción ${i}/${iteraciones}: ${nombre}`);
        
        const startTime = performance.now();
        input.value = txt;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Simular espera de respuesta asíncrona
        await new Promise(r => setTimeout(r, 800)); 
        const endTime = performance.now();
        const tiempoRespuesta = (endTime - startTime - 800).toFixed(2); // Restamos el timeout artificial

        log(`   ⏲️ Interfaz lista en ${tiempoRespuesta}ms`, "#6ee7b7");

        if (i < iteraciones) {
            const pausa = Math.floor(Math.random() * (10000 - 5000 + 1)) + 5000;
            log(`   ☕ Usuario haciendo pausa de ${(pausa/1000).toFixed(1)}s...`, "#94a3b8");
            await new Promise(r => setTimeout(r, pausa));
        }
    }

    log("🏁 SIMULACIÓN HUMANA FINALIZADA", "#22d3ee");
}

// Para ejecutar automáticamente al cargar (opcional):
// simularEscaneos(100);
