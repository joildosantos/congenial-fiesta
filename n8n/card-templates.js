/**
 * Gerador de Cards Sociais - Jornal Espaco do Povo
 *
 * Templates HTML para renderizacao via Browserless.
 * Cada funcao retorna HTML completo de um card 1080x1080 (Instagram)
 * ou 1200x630 (Facebook/WhatsApp/Telegram).
 *
 * Uso no n8n: o node Code chama getCardHtml(dados) e recebe o HTML pronto.
 */

const BRAND = {
  nome: 'ESPAÇO DO POVO',
  subtitulo: 'JORNAL COMUNITÁRIO',
  handle: '@espacodopovo',
  site: 'www.espacodopovo.com.br',
  corPrimaria: '#c0392b',
  corSecundaria: '#1a1a2e',
  corLime: '#CCFF00',
  logoSvg: '<svg viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>',
};

const CORES_EDITORIA = {
  'URGENTE': '#e74c3c',
  'POLITICA': '#2c3e50',
  'SAUDE': '#27ae60',
  'EDUCACAO': '#2980b9',
  'TRANSPORTE': '#e67e22',
  'CULTURA': '#8e44ad',
  'ESPORTE': '#c0392b',
  'HISTORIA': '#d4a017',
  'SERVICOS': '#16a085',
  'GASTRONOMIA': '#d35400',
  'EVENTOS': '#8e44ad',
  'MEIO AMBIENTE': '#27ae60',
  'EMPREENDEDORISMO': '#2c3e50',
  'NOTICIAS': '#c0392b',
  'CURIOSIDADE': '#f39c12',
  'EDITORIAL': '#1a1a2e',
  'COLUNA': '#34495e',
};

/**
 * Template 1: Noticia Padrao (1080x1080 para Instagram / 1200x630 para FB)
 * - Foto de fundo com overlay
 * - Logo no topo esquerdo
 * - Badge de editoria
 * - Titulo grande
 * - Barra inferior com handle e site
 */
function templateNoticia(dados, formato = '1080x1080') {
  const { titulo, editoria, imagemFundo, autor } = dados;
  const cor = CORES_EDITORIA[(editoria || 'NOTICIAS').toUpperCase()] || '#c0392b';
  const isSquare = formato === '1080x1080';
  const w = isSquare ? 1080 : 1200;
  const h = isSquare ? 1080 : 630;
  const titleSize = isSquare ? 52 : 44;
  const titleMaxW = isSquare ? 900 : 1000;

  const lines = breakTitle(titulo, isSquare ? 24 : 30);

  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
* { margin:0; padding:0; box-sizing:border-box; }
body { width:${w}px; height:${h}px; overflow:hidden; font-family:'Inter',sans-serif; }
.card {
  width:${w}px; height:${h}px; position:relative;
  background: #0a0a0a;
  display:flex; flex-direction:column; justify-content:space-between;
}
.bg {
  position:absolute; top:0; left:0; right:0; bottom:0;
  ${imagemFundo ? `background: url('${imagemFundo}') center/cover no-repeat;` : ''}
  opacity: 0.45;
}
.bg::after {
  content:''; position:absolute; bottom:0; left:0; right:0; height:70%;
  background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.6) 40%, transparent 100%);
}
.card > * { position:relative; z-index:1; }

/* TOPO */
.top {
  display:flex; justify-content:space-between; align-items:flex-start;
  padding: ${isSquare ? '36px 40px' : '24px 36px'};
}
.logo-area { display:flex; align-items:center; gap:14px; }
.logo-icon {
  width:${isSquare ? 54 : 46}px; height:${isSquare ? 54 : 46}px;
  background: ${BRAND.corLime};
  border-radius: 50%;
  display:flex; align-items:center; justify-content:center;
}
.logo-icon svg { width:${isSquare ? 28 : 24}px; height:${isSquare ? 28 : 24}px; }
.logo-text {
  color:white; font-weight:800; font-size:${isSquare ? 18 : 15}px;
  line-height:1.15; text-transform:uppercase; letter-spacing:1px;
}
.logo-text .sub {
  font-size:${isSquare ? 10 : 9}px; font-weight:600;
  opacity:0.6; letter-spacing:2.5px; display:block; margin-top:2px;
}
.badge {
  background: ${cor}; color:white;
  padding: 8px 20px; border-radius:4px;
  font-size:${isSquare ? 13 : 11}px; font-weight:800;
  letter-spacing:3px; text-transform:uppercase;
}

/* CONTEUDO */
.content {
  flex:1; display:flex; flex-direction:column; justify-content:flex-end;
  padding: ${isSquare ? '0 44px 20px' : '0 40px 12px'};
}
.titulo {
  color:white; font-size:${titleSize}px; font-weight:900;
  line-height:1.18; max-width:${titleMaxW}px;
  text-shadow: 0 2px 30px rgba(0,0,0,0.7);
}
.accent-line {
  width:60px; height:4px; background:${cor};
  border-radius:2px; margin-top:${isSquare ? 20 : 14}px;
}
${autor ? `.autor {
  color:rgba(255,255,255,0.75); font-size:${isSquare ? 16 : 14}px;
  font-weight:600; margin-top:12px;
}` : ''}

/* RODAPE */
.bottom {
  display:flex; justify-content:space-between; align-items:center;
  padding: ${isSquare ? '20px 44px 32px' : '16px 40px 22px'};
  border-top: 1px solid rgba(255,255,255,0.08);
}
.handle {
  color:rgba(255,255,255,0.5); font-size:${isSquare ? 15 : 13}px;
  font-weight:600; letter-spacing:0.5px;
}
.site-url {
  color:${cor}; font-size:${isSquare ? 14 : 12}px;
  font-weight:700; letter-spacing:0.5px;
}
</style></head><body>
<div class="card">
  <div class="bg"></div>
  <div class="top">
    <div class="logo-area">
      <div class="logo-icon">${BRAND.logoSvg}</div>
      <div class="logo-text">${BRAND.nome}<span class="sub">${BRAND.subtitulo}</span></div>
    </div>
    <div class="badge">${(editoria || 'NOTICIAS').toUpperCase()}</div>
  </div>
  <div class="content">
    <div class="titulo">${lines.join('<br>')}</div>
    <div class="accent-line"></div>
    ${autor ? `<div class="autor">Por ${autor}</div>` : ''}
  </div>
  <div class="bottom">
    <div class="handle">${BRAND.handle}</div>
    <div class="site-url">${BRAND.site}</div>
  </div>
</div>
</body></html>`;
}


/**
 * Template 2: Urgente (1080x1080)
 * - Fundo vermelho escuro + overlay
 * - Selo URGENTE grande
 * - Titulo em destaque
 */
function templateUrgente(dados, formato = '1080x1080') {
  const { titulo, imagemFundo } = dados;
  const isSquare = formato === '1080x1080';
  const w = isSquare ? 1080 : 1200;
  const h = isSquare ? 1080 : 630;

  const lines = breakTitle(titulo, isSquare ? 22 : 28);

  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
* { margin:0; padding:0; box-sizing:border-box; }
body { width:${w}px; height:${h}px; overflow:hidden; font-family:'Inter',sans-serif; }
.card {
  width:${w}px; height:${h}px; position:relative;
  background: linear-gradient(135deg, #1a0000 0%, #3d0000 30%, #8b0000 100%);
  display:flex; flex-direction:column; justify-content:space-between;
}
.bg {
  position:absolute; top:0; left:0; right:0; bottom:0;
  ${imagemFundo ? `background: url('${imagemFundo}') center/cover;` : ''}
  opacity:0.2;
}
.bg::after {
  content:''; position:absolute; top:0; left:0; right:0; bottom:0;
  background: linear-gradient(180deg, rgba(139,0,0,0.8) 0%, rgba(30,0,0,0.95) 100%);
}
.card > * { position:relative; z-index:1; }
.top {
  display:flex; justify-content:space-between; align-items:flex-start;
  padding: ${isSquare ? '36px 44px' : '24px 36px'};
}
.logo-area { display:flex; align-items:center; gap:14px; }
.logo-icon {
  width:${isSquare ? 54 : 46}px; height:${isSquare ? 54 : 46}px;
  background:${BRAND.corLime}; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
}
.logo-icon svg { width:${isSquare ? 28 : 24}px; height:${isSquare ? 28 : 24}px; }
.logo-text { color:white; font-weight:800; font-size:${isSquare ? 18 : 15}px; line-height:1.15; text-transform:uppercase; letter-spacing:1px; }
.logo-text .sub { font-size:${isSquare ? 10 : 9}px; font-weight:600; opacity:0.6; letter-spacing:2.5px; display:block; margin-top:2px; }
.urgente-badge {
  background:white; color:#c0392b;
  padding:10px 28px; border-radius:4px;
  font-size:${isSquare ? 18 : 15}px; font-weight:900;
  letter-spacing:5px; text-transform:uppercase;
  animation: pulse 1.5s ease-in-out infinite;
}
.content {
  flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center;
  padding: ${isSquare ? '0 50px' : '0 44px'}; text-align:center;
}
.urgente-icon { font-size:${isSquare ? 48 : 36}px; margin-bottom:20px; }
.titulo {
  color:white; font-size:${isSquare ? 56 : 46}px; font-weight:900;
  line-height:1.15; max-width:${isSquare ? 900 : 1000}px;
  text-shadow: 0 4px 40px rgba(0,0,0,0.5);
}
.red-line { width:80px; height:4px; background:white; border-radius:2px; margin:24px auto 0; opacity:0.5; }
.bottom {
  display:flex; justify-content:space-between; align-items:center;
  padding: ${isSquare ? '20px 44px 32px' : '16px 40px 22px'};
  border-top:1px solid rgba(255,255,255,0.15);
}
.handle { color:rgba(255,255,255,0.6); font-size:${isSquare ? 15 : 13}px; font-weight:600; }
.site-url { color:white; font-size:${isSquare ? 14 : 12}px; font-weight:700; opacity:0.7; }
</style></head><body>
<div class="card">
  <div class="bg"></div>
  <div class="top">
    <div class="logo-area">
      <div class="logo-icon">${BRAND.logoSvg}</div>
      <div class="logo-text">${BRAND.nome}<span class="sub">${BRAND.subtitulo}</span></div>
    </div>
    <div class="urgente-badge">URGENTE</div>
  </div>
  <div class="content">
    <div class="titulo">${lines.join('<br>')}</div>
    <div class="red-line"></div>
  </div>
  <div class="bottom">
    <div class="handle">${BRAND.handle}</div>
    <div class="site-url">${BRAND.site}</div>
  </div>
</div>
</body></html>`;
}


/**
 * Template 3: Colunista / Opiniao (1080x1080)
 * - Foto do autor em circulo
 * - Nome e titulo do colunista
 * - Fundo escuro elegante
 */
function templateColunista(dados, formato = '1080x1080') {
  const { titulo, editoria, autor, autorFoto, autorTitulo, imagemFundo } = dados;
  const cor = CORES_EDITORIA[(editoria || 'COLUNA').toUpperCase()] || '#34495e';
  const isSquare = formato === '1080x1080';
  const w = isSquare ? 1080 : 1200;
  const h = isSquare ? 1080 : 630;

  const lines = breakTitle(titulo, isSquare ? 26 : 32);

  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap');
* { margin:0; padding:0; box-sizing:border-box; }
body { width:${w}px; height:${h}px; overflow:hidden; font-family:'Inter',sans-serif; }
.card {
  width:${w}px; height:${h}px; position:relative;
  background: linear-gradient(160deg, #0f0f1a 0%, #1a1a2e 40%, #16213e 100%);
  display:flex; flex-direction:column; justify-content:space-between;
}
.card > * { position:relative; z-index:1; }
.top {
  display:flex; justify-content:space-between; align-items:flex-start;
  padding: ${isSquare ? '36px 44px' : '24px 36px'};
}
.logo-area { display:flex; align-items:center; gap:14px; }
.logo-icon {
  width:${isSquare ? 48 : 42}px; height:${isSquare ? 48 : 42}px;
  background:${BRAND.corLime}; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
}
.logo-icon svg { width:${isSquare ? 26 : 22}px; height:${isSquare ? 26 : 22}px; }
.logo-text { color:white; font-weight:800; font-size:${isSquare ? 16 : 14}px; line-height:1.15; text-transform:uppercase; letter-spacing:1px; }
.logo-text .sub { font-size:${isSquare ? 9 : 8}px; font-weight:600; opacity:0.5; letter-spacing:2.5px; display:block; margin-top:2px; }
.badge { background:${cor}; color:white; padding:7px 18px; border-radius:4px; font-size:${isSquare ? 11 : 10}px; font-weight:800; letter-spacing:3px; text-transform:uppercase; }

.content {
  flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center;
  padding: ${isSquare ? '0 60px' : '0 50px'}; text-align:center;
}
.aspas { font-size:80px; color:${cor}; opacity:0.4; font-family:'Playfair Display',serif; line-height:0.5; margin-bottom:16px; }
.titulo {
  color:white; font-size:${isSquare ? 46 : 38}px; font-weight:800;
  line-height:1.2; max-width:${isSquare ? 860 : 960}px;
  font-family:'Playfair Display',serif; font-style:italic;
}
.accent-line { width:60px; height:3px; background:${cor}; border-radius:2px; margin:24px auto; }
.autor-area { display:flex; align-items:center; gap:16px; justify-content:center; }
.autor-foto {
  width:${isSquare ? 64 : 52}px; height:${isSquare ? 64 : 52}px;
  border-radius:50%; border:3px solid ${cor};
  ${autorFoto ? `background: url('${autorFoto}') center/cover;` : `background:${cor}; display:flex; align-items:center; justify-content:center; color:white; font-weight:800; font-size:22px;`}
}
.autor-info { text-align:left; }
.autor-nome { color:white; font-size:${isSquare ? 18 : 15}px; font-weight:700; }
.autor-cargo { color:rgba(255,255,255,0.5); font-size:${isSquare ? 13 : 11}px; font-weight:500; margin-top:2px; }

.bottom {
  display:flex; justify-content:space-between; align-items:center;
  padding: ${isSquare ? '20px 44px 32px' : '16px 40px 22px'};
  border-top:1px solid rgba(255,255,255,0.08);
}
.handle { color:rgba(255,255,255,0.5); font-size:${isSquare ? 15 : 13}px; font-weight:600; }
.site-url { color:${cor}; font-size:${isSquare ? 14 : 12}px; font-weight:700; }
</style></head><body>
<div class="card">
  <div class="top">
    <div class="logo-area">
      <div class="logo-icon">${BRAND.logoSvg}</div>
      <div class="logo-text">${BRAND.nome}<span class="sub">${BRAND.subtitulo}</span></div>
    </div>
    <div class="badge">${(editoria || 'COLUNA').toUpperCase()}</div>
  </div>
  <div class="content">
    <div class="aspas">&ldquo;</div>
    <div class="titulo">${lines.join('<br>')}</div>
    <div class="accent-line"></div>
    <div class="autor-area">
      <div class="autor-foto">${!autorFoto ? (autor || 'A').charAt(0).toUpperCase() : ''}</div>
      <div class="autor-info">
        <div class="autor-nome">${autor || 'Colunista'}</div>
        <div class="autor-cargo">${autorTitulo || 'Colunista convidado'}</div>
      </div>
    </div>
  </div>
  <div class="bottom">
    <div class="handle">${BRAND.handle}</div>
    <div class="site-url">${BRAND.site}</div>
  </div>
</div>
</body></html>`;
}


/**
 * Template 4: Texto Limpo / Frase de Impacto (1080x1080)
 * - Sem imagem de fundo
 * - Fundo gradiente escuro ou colorido
 * - Otimo para frases, dados, estatisticas
 */
function templateTextoLimpo(dados, formato = '1080x1080') {
  const { titulo, editoria, subtexto } = dados;
  const cor = CORES_EDITORIA[(editoria || 'NOTICIAS').toUpperCase()] || '#c0392b';
  const isSquare = formato === '1080x1080';
  const w = isSquare ? 1080 : 1200;
  const h = isSquare ? 1080 : 630;

  const lines = breakTitle(titulo, isSquare ? 22 : 28);

  return `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
* { margin:0; padding:0; box-sizing:border-box; }
body { width:${w}px; height:${h}px; overflow:hidden; font-family:'Inter',sans-serif; }
.card {
  width:${w}px; height:${h}px; position:relative;
  background: linear-gradient(160deg, #0a0a14 0%, ${cor}22 50%, #0a0a14 100%);
  display:flex; flex-direction:column; justify-content:space-between;
}
.card > * { position:relative; z-index:1; }
.deco-circle {
  position:absolute; width:${isSquare ? 500 : 400}px; height:${isSquare ? 500 : 400}px;
  border-radius:50%; background:${cor}; opacity:0.06;
  top:${isSquare ? -100 : -150}px; right:${isSquare ? -100 : -80}px;
}
.top {
  display:flex; justify-content:space-between; align-items:flex-start;
  padding: ${isSquare ? '36px 44px' : '24px 36px'};
}
.logo-area { display:flex; align-items:center; gap:14px; }
.logo-icon {
  width:${isSquare ? 48 : 42}px; height:${isSquare ? 48 : 42}px;
  background:${BRAND.corLime}; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
}
.logo-icon svg { width:${isSquare ? 26 : 22}px; height:${isSquare ? 26 : 22}px; }
.logo-text { color:white; font-weight:800; font-size:${isSquare ? 16 : 14}px; line-height:1.15; text-transform:uppercase; letter-spacing:1px; }
.logo-text .sub { font-size:${isSquare ? 9 : 8}px; font-weight:600; opacity:0.5; letter-spacing:2.5px; display:block; margin-top:2px; }
.badge { background:${cor}; color:white; padding:7px 18px; border-radius:4px; font-size:${isSquare ? 11 : 10}px; font-weight:800; letter-spacing:3px; text-transform:uppercase; }

.content {
  flex:1; display:flex; flex-direction:column; justify-content:center;
  padding: ${isSquare ? '0 60px' : '0 50px'};
}
.left-accent {
  width:5px; height:60px; background:${cor}; border-radius:3px;
  position:absolute; left:${isSquare ? 44 : 36}px;
}
.titulo {
  color:white; font-size:${isSquare ? 54 : 44}px; font-weight:900;
  line-height:1.18; max-width:${isSquare ? 900 : 1000}px;
}
.subtexto {
  color:rgba(255,255,255,0.55); font-size:${isSquare ? 20 : 17}px;
  font-weight:500; margin-top:20px; max-width:${isSquare ? 800 : 900}px;
  line-height:1.5;
}

.bottom {
  display:flex; justify-content:space-between; align-items:center;
  padding: ${isSquare ? '20px 44px 32px' : '16px 40px 22px'};
  border-top:1px solid rgba(255,255,255,0.08);
}
.handle { color:rgba(255,255,255,0.5); font-size:${isSquare ? 15 : 13}px; font-weight:600; }
.site-url { color:${cor}; font-size:${isSquare ? 14 : 12}px; font-weight:700; }
</style></head><body>
<div class="card">
  <div class="deco-circle"></div>
  <div class="top">
    <div class="logo-area">
      <div class="logo-icon">${BRAND.logoSvg}</div>
      <div class="logo-text">${BRAND.nome}<span class="sub">${BRAND.subtitulo}</span></div>
    </div>
    <div class="badge">${(editoria || 'NOTICIAS').toUpperCase()}</div>
  </div>
  <div class="content">
    <div class="titulo">${lines.join('<br>')}</div>
    ${subtexto ? `<div class="subtexto">${subtexto}</div>` : ''}
  </div>
  <div class="bottom">
    <div class="handle">${BRAND.handle}</div>
    <div class="site-url">${BRAND.site}</div>
  </div>
</div>
</body></html>`;
}


// === UTILIDADES ===

function breakTitle(titulo, maxCharsPerLine) {
  const words = (titulo || '').split(' ');
  let lines = [];
  let currentLine = '';
  for (const word of words) {
    if ((currentLine + ' ' + word).trim().length > maxCharsPerLine) {
      if (currentLine) lines.push(currentLine.trim());
      currentLine = word;
    } else {
      currentLine = (currentLine + ' ' + word).trim();
    }
  }
  if (currentLine) lines.push(currentLine.trim());
  if (lines.length > 5) {
    lines = lines.slice(0, 5);
    lines[4] = lines[4].substring(0, maxCharsPerLine - 3) + '...';
  }
  return lines;
}

// Seleciona template automaticamente baseado na editoria/tipo
function getCardHtml(dados, formato = '1080x1080') {
  const editoria = (dados.editoria || '').toUpperCase();
  const tipo = (dados.tipo || '').toLowerCase();

  if (editoria === 'URGENTE') {
    return templateUrgente(dados, formato);
  }
  if (tipo === 'coluna' || tipo === 'opiniao' || editoria === 'COLUNA' || editoria === 'EDITORIAL') {
    return templateColunista(dados, formato);
  }
  if (!dados.imagemFundo && !dados.featuredImage) {
    return templateTextoLimpo(dados, formato);
  }
  return templateNoticia(dados, formato);
}

// Exporta para uso no n8n Code node
module.exports = { templateNoticia, templateUrgente, templateColunista, templateTextoLimpo, getCardHtml, breakTitle, BRAND, CORES_EDITORIA };
