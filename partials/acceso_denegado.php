<?php
/**
 * partials/acceso_denegado.php
 * Overlay de acceso denegado — se incluye cuando el usuario
 * no tiene permiso para el módulo que intenta ver.
 * Requiere que la página ya tenga cargado main.css y theme-engine.js
 */

$modulo   = $GLOBALS['_modulo_denegado']  ?? 'este módulo';
$rolUser  = $_SESSION['usuario_rol']       ?? 'vendedor';
$rolLabel = ROL_LABELS[$rolUser]           ?? ucfirst($rolUser);
$nombre   = $_SESSION['usuario_nombre']    ?? 'Usuario';
?>

<!-- ══ OVERLAY ACCESO DENEGADO ══════════════════════════════ -->
<div id="overlay-acceso-denegado" style="
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: overlayFadeIn .25s ease;
">
    <!-- Fondo desenfocado -->
    <div style="
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.72);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    "></div>

    <!-- Tarjeta principal -->
    <div style="
        position: relative;
        background: var(--bg-surface, #111820);
        border: 1px solid var(--red-border, rgba(248,113,113,0.35));
        border-top: 4px solid var(--red, #f87171);
        border-radius: 20px;
        padding: 44px 40px 36px;
        max-width: 480px;
        width: 100%;
        text-align: center;
        box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(248,113,113,0.1);
        animation: cardSlideUp .3s ease;
    ">
        <!-- Icono candado animado -->
        <div style="
            width: 80px; height: 80px;
            background: var(--red-bg, rgba(248,113,113,0.1));
            border: 2px solid var(--red-border, rgba(248,113,113,0.35));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            animation: lockPulse 2s ease-in-out infinite;
        ">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none"
                 stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                <circle cx="12" cy="16" r="1" fill="#f87171"/>
            </svg>
        </div>

        <!-- Título -->
        <h2 style="
            font-family: var(--font-display, 'Syne', sans-serif);
            font-size: 22px;
            font-weight: 800;
            color: var(--text, #e8edf2);
            margin: 0 0 10px;
            letter-spacing: -0.02em;
            line-height: 1.2;
        ">Acceso Restringido</h2>

        <!-- Subtítulo -->
        <p style="
            font-size: 14px;
            color: var(--red, #f87171);
            font-weight: 600;
            margin: 0 0 20px;
            letter-spacing: 0.02em;
        ">🔒 No tienes el rol indicado para acceder a este módulo</p>

        <!-- Descripción -->
        <div style="
            background: var(--red-bg, rgba(248,113,113,0.08));
            border: 1px solid var(--red-border, rgba(248,113,113,0.25));
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 24px;
            text-align: left;
        ">
            <p style="
                font-size: 13.5px;
                color: var(--text-sub, #8a95a3);
                line-height: 1.7;
                margin: 0 0 10px;
            ">
                Hola <strong style="color:var(--text,#e8edf2)"><?= htmlspecialchars($nombre) ?></strong>,
                el módulo <strong style="color:var(--text,#e8edf2)"><?= htmlspecialchars($modulo) ?></strong>
                contiene información confidencial del negocio y está reservado exclusivamente
                para usuarios con rol de <strong style="color:var(--text,#e8edf2)">Administrador</strong>.
            </p>
            <p style="
                font-size: 13px;
                color: var(--text-hint, #48555f);
                margin: 0;
                display: flex;
                align-items: center;
                gap: 6px;
            ">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Tu rol actual: <strong style="color:var(--amber,#fbbf24)"><?= htmlspecialchars($rolLabel) ?></strong>
                &nbsp;·&nbsp; Contacta al administrador si necesitas acceso.
            </p>
        </div>

        <!-- Botones -->
        <div style="display:flex; gap:10px; justify-content:center;">
            <a href="javascript:history.back()" style="
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 10px 20px;
                background: var(--bg-surface2, #19222d);
                border: 1px solid var(--border-md, rgba(255,255,255,0.12));
                border-radius: 8px;
                color: var(--text-sub, #8a95a3);
                font-size: 13px;
                font-weight: 500;
                font-family: var(--font-body, 'DM Sans', sans-serif);
                text-decoration: none;
                transition: all 0.16s ease;
            " onmouseover="this.style.background='var(--bg-surface3)';this.style.color='var(--text)'"
               onmouseout="this.style.background='var(--bg-surface2)';this.style.color='var(--text-sub)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Volver atrás
            </a>

            <a href="index.php" style="
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 10px 20px;
                background: var(--accent, #2563eb);
                border: 1px solid transparent;
                border-radius: 8px;
                color: #fff;
                font-size: 13px;
                font-weight: 600;
                font-family: var(--font-body, 'DM Sans', sans-serif);
                text-decoration: none;
                transition: all 0.16s ease;
            " onmouseover="this.style.background='var(--accent-hover)'"
               onmouseout="this.style.background='var(--accent)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                </svg>
                Ir al Dashboard
            </a>
        </div>

        <!-- Badge de módulo -->
        <p style="
            margin-top: 22px;
            font-size: 11px;
            color: var(--text-hint, #48555f);
            letter-spacing: 0.06em;
        ">
            LAVANDERÍA SUPERIOR · SISTEMA DE GESTIÓN · ACCESO DENEGADO
        </p>
    </div>
</div>

<style>
@keyframes overlayFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes cardSlideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
}
@keyframes lockPulse {
    0%, 100% { box-shadow: 0 0 0 0   rgba(248,113,113,0.3); }
    50%       { box-shadow: 0 0 0 10px rgba(248,113,113,0);  }
}
</style>
