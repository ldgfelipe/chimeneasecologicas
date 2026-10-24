<?php
require_once __DIR__ . '/api/db.php';
$cfg = db_load_settings();
if ($cfg === null || empty($cfg)) {
    $jsonFile = __DIR__ . '/config.json';
    if (file_exists($jsonFile)) {
        $cfg = json_decode(file_get_contents($jsonFile), true) ?: array();
    } else {
        $cfg = array();
    }
}
$customHead = $cfg['custom_head'] ?? '';
$customBodyStart = $cfg['custom_body_start'] ?? '';
$customBodyEnd = $cfg['custom_body_end'] ?? '';
$cb = !empty($cfg['cacheBust']) ? '?v=' . $cfg['cacheBust'] : '';
$logoSrc = $cfg['images']['logo'] ?? 'assets/img/logo.jpg';
$heroSrc = $cfg['images']['hero'] ?? 'assets/img/hero.jpg';
$showroomSrc = $cfg['images']['showroom'] ?? 'assets/img/showroom.jpg';
$whatsapp = preg_replace('/\D+/', '', $cfg['whatsapp'] ?? '522227150505');
$waMsg = urlencode($cfg['whatsappMessage'] ?? 'Hola, me interesa una cotización');
$waLink = 'https://wa.me/' . $whatsapp . '?text=' . $waMsg;
$mapsUrl = $cfg['googleMapsUrl'] ?? '#';
$mapsEmbed = $cfg['googleMapsEmbed'] ?? '';
$ig = $cfg['instagram'] ?? '#';
$fb = $cfg['facebook'] ?? '#';
$tt = $cfg['tiktok'] ?? '#';
$gbp = $cfg['gbpUrl'] ?? '#';
$email = $cfg['email'] ?? '';
$address = $cfg['address'] ?? '';
$siteName = $cfg['siteName'] ?? 'Chimeneas Ecológicas';
$formsEndpoint = $cfg['formsEndpoint'] ?? 'api/contact.php';
$analyticsId = $cfg['analyticsId'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($siteName); ?> | Chimeneas de bioetanol y decoración premium</title>
<meta name="description" content="Chimeneas de bioetanol y artículos de decoración para el hogar. Calor limpio, diseño premium y respeto por el medio ambiente. Showroom en Puebla.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Contrail+One&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Open+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=18">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "<?php echo htmlspecialchars($siteName); ?>",
  "image": "<?php echo htmlspecialchars($logoSrc); ?>",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Calzada Zavaleta 2302",
    "addressLocality": "Puebla",
    "addressCountry": "MX"
  },
  "telephone": "+52-222-715-0505",
  "url": "https://chimeneasecologicas.com.mx/",
  "sameAs": [
    "<?php echo htmlspecialchars($ig); ?>",
    "<?php echo htmlspecialchars($fb); ?>",
    "<?php echo htmlspecialchars($tt); ?>"
  ]
}
</script>
<!-- GSAP -->
<script src="assets/js/gsap.min.js?v=7" defer></script>
<script src="assets/js/ScrollTrigger.min.js?v=7" defer></script>
<?php if (!empty($analyticsId) && $analyticsId !== 'G-XXXXXXXXXX'): ?>
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($analyticsId); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo htmlspecialchars($analyticsId); ?>');
</script>
<?php endif; ?>
<?php echo $customHead; ?>
</head>
<body>
<?php echo $customBodyStart; ?>
  <header class="site-header">
    <div class="container header-inner">
      <a href="#inicio" class="logo"><img id="img-logo" src="assets/img/logo.jpg" alt="Chimeneas Ecológicas"></a>
      <nav class="nav">
        <a href="#servicios">Servicios</a>
        <a href="#productos">Productos</a>
        <a href="#showroom">Showroom</a>
        <a href="#contacto">Contacto</a>
      </nav>
      <a id="wa-header" href="#" class="btn btn-primary btn-sm" target="_blank" rel="noopener">WhatsApp</a>
      <button class="nav-toggle" aria-label="Menú">☰</button>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section id="inicio" class="hero">
      <div class="hero-bg" id="hero-bg"></div>
      <div class="container hero-content">
        <p class="eyebrow">Fabricantes y distribuidores · Puebla</p>
        <h1 class="hero-title">Chimeneas y decoración que transforman tu hogar</h1>
        <p class="hero-sub">Más de 10 años creando espacios con fuego ecológico. Bioetanol, gas, eléctricas y vapor de agua. Sin humo, sin obra, sin complicaciones.</p>
        <div class="hero-actions">
          <a href="#productos" class="btn btn-primary">Ver catálogo</a>
          <a id="wa-hero" href="#" class="btn btn-outline" target="_blank" rel="noopener">Cotizar por WhatsApp</a>
        </div>
      </div>
      <div class="scroll-indicator">↓</div>
    </section>

    <!-- STATS -->
    <section class="stats-section reveal">
      <div class="container stats-grid">
        <div class="stat "><span class="stat-num" data-count="10">10</span><span class="stat-label">+ Años de experiencia</span></div>
        <div class="stat "><span class="stat-num" data-count="40">40</span><span class="stat-label">Modelos en catálogo</span></div>
        <div class="stat "><span class="stat-num" data-count="4">4</span><span class="stat-label">Tipos de chimeneas</span></div>
        <div class="stat "><span class="stat-num" data-count="100">100</span><span class="stat-label">% Ecológico</span></div>
      </div>
    </section>

    <!-- TIPOS DE CHIMENEAS -->
    <section id="servicios" class="section section-light reveal">
      <div class="container">
        <h2 class="section-title ">Tipos de chimeneas</h2>
        <p class="section-sub ">Elige el tipo de chimenea que más se ajuste a tus necesidades.</p>
        <div class="features">
          <div class="feature "><div class="feature-icon">🌿</div><div><h3>Bioetanol</h3><p>Ecológicas, sin humo ni cenizas, seguras y prácticas.</p></div></div>
          <div class="feature "><div class="feature-icon">🔥</div><div><h3>Gas</h3><p>Seguras, versátiles y con opción a control remoto.</p></div></div>
          <div class="feature "><div class="feature-icon">⚡</div><div><h3>Eléctricas</h3><p>Prácticas, decorativas y fáciles de instalar.</p></div></div>
          <div class="feature "><div class="feature-icon">💨</div><div><h3>Vapor de agua</h3><p>Innovadoras, ideales para un toque contemporáneo.</p></div></div>
        </div>
      </div>
    </section>

    <!-- PRODUCTOS -->
    <section id="productos" class="section reveal">
      <div class="container">
        <h2 class="section-title ">Catálogo 2026-2027</h2>
        <p class="section-sub ">Chimeneas de bioetanol, gas, eléctricas, vapor de agua, fire pits, accesorios y combustible.</p>
        <div class="catalog-filter">
          <div class="search-box">
            <input type="text" id="product-search" placeholder="Buscar producto, modelo o precio…" autocomplete="off">
            <button type="button" id="search-clear" class="search-clear" aria-label="Limpiar búsqueda" style="display:none;">✕</button>
          </div>
          <select id="category-filter" aria-label="Filtrar por categoría">
            <option value="">Todas las categorías</option>
          </select>
          <select id="sort-filter" aria-label="Ordenar por">
            <option value="default">Ordenar: Destacados</option>
            <option value="price-asc">Precio: menor a mayor</option>
            <option value="price-desc">Precio: mayor a menor</option>
          </select>
        </div>
        <p id="catalog-count" class="catalog-count"></p>
        <div class="products-grid" id="products-grid"></div>
      </div>
    </section>

    <!-- MARQUEE -->
    <section class="marquee-section">
      <div class="marquee-track" id="marquee-track"></div>
    </section>

    <!-- SHOWROOM -->
    <section id="showroom" class="section section-light reveal">
      <div class="container">
        <h2 class="section-title ">Nuestro showroom</h2>
        <p class="section-sub ">Visítanos en Calzada Zavaleta 2302, Puebla. Conoce nuestras chimeneas en persona.</p>
        <div class="showroom-grid">
          <img id="img-showroom" src="assets/img/showroom.jpg" alt="Showroom" loading="lazy" class="">
          <div class="showroom-info ">
            <p><strong>Dirección:</strong> Calzada Zavaleta 2302, Puebla, México</p>
            <p><strong>WhatsApp:</strong> <a id="wa-showroom" href="#">+52 (222) 715 0505</a></p>
            <p><strong>Google Maps:</strong> <a id="maps-link" href="#" target="_blank" rel="noopener">Ver ubicación</a></p>
            <div class="showroom-actions">
              <a id="wa-visit" href="#" class="btn btn-primary" target="_blank" rel="noopener">Agendar visita</a>
              <a id="wa-catalog" href="#" class="btn btn-outline" target="_blank" rel="noopener">Pedir catálogo</a>
            </div>
          </div>
        </div>
        <div class="map-embed">
          <iframe id="map-iframe" src="" width="100%" height="360" style="border:0;border-radius:16px;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
      </div>
    </section>

    <!-- CONTACTO -->
    <section id="contacto" class="section reveal">
      <div class="container">
        <h2 class="section-title ">Contacto</h2>
        <p class="section-sub ">Solicita una cotización o agenda una visita al showroom.</p>
        <div class="contact-grid">
          <form id="contact-form" class="contact-form" action="api/contact.php" method="get">
            <!-- Campos trampa invisibles (anti-spam): un bot los llena, el envío se descarta -->
            <div class="hp-field" aria-hidden="true">
              <input type="text" name="website" tabindex="-1" autocomplete="off">
              <input type="text" name="company" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group"><label>Nombre *</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Teléfono / WhatsApp</label><input type="text" name="phone"></div>

            <div class="form-group">
              <label>Producto de interés *</label>
              <select name="product" id="product-select" required>
                <option value="">Selecciona un producto…</option>
              </select>
            </div>

            <div class="form-group">
              <label>¿Qué deseas hacer? *</label>
              <label class="radio"><input type="radio" name="interest" value="cita"> Agendar cita para visitar el showroom</label>
              <label class="radio"><input type="radio" name="interest" value="informacion" checked> Solo solicitar información del producto</label>
            </div>

            <div class="form-group" id="date-group" style="display:none;">
              <label>Fecha disponible para tu cita *</label>
              <input type="date" name="appointment_date" id="appointment-date">
            </div>

            <div class="form-group"><label>Mensaje *</label><textarea name="message" required placeholder="Cuéntanos qué necesitas…"></textarea></div>

            <div class="form-group">
              <label>Verificación de seguridad *</label>
              <div class="captcha-row">
                <span id="captcha-question" class="captcha-q">…</span>
                <input type="text" name="captcha_answer" id="captcha-answer" inputmode="numeric" placeholder="?" required>
                <button type="button" id="captcha-refresh" class="btn btn-outline btn-sm" aria-label="Nuevo código">↻</button>
              </div>
              <input type="hidden" name="captcha_a" id="captcha-a">
              <input type="hidden" name="captcha_b" id="captcha-b">
              <input type="hidden" name="captcha_token" id="captcha-token">
              <input type="hidden" name="captcha_time" id="captcha-time">
            </div>

            <div class="form-group">
              <label class="check"><input type="checkbox" name="consent_privacy" value="1" required> He leído y acepto el <a href="aviso-privacidad.html" target="_blank" rel="noopener">aviso de privacidad</a> *</label>
              <label class="check"><input type="checkbox" name="consent_ads" value="1" required> Acepto recibir información publicitaria a mi correo *</label>
            </div>

            <button type="submit" class="btn btn-primary">Enviar solicitud</button>
            <div id="form-msg" class="form-msg"></div>
          </form>
          <div class="contact-info ">
            <h3>Otras formas de contacto</h3>
            <p><a id="wa-contact" href="#" target="_blank" rel="noopener">WhatsApp</a></p>
            <p><a id="ig-link" href="#" target="_blank" rel="noopener">Instagram</a></p>
            <p><a id="fb-link" href="#" target="_blank" rel="noopener">Facebook</a></p>
            <p><a id="tt-link" href="#" target="_blank" rel="noopener">TikTok</a></p>
            <p><a href="aviso-privacidad.html" target="_blank" rel="noopener">Aviso de privacidad</a></p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- MODAL -->
  <div class="modal-overlay" id="product-modal">
    <div class="modal">
      <button class="modal-close" id="modal-close" aria-label="Cerrar">&times;</button>
      <div class="modal-gallery" id="modal-gallery"></div>
      <div class="modal-body">
        <span id="modal-cat" class="modal-cat"></span>
        <h2 id="modal-title"></h2>
        <div class="modal-price" id="modal-price"></div>
        <p id="modal-specs" class="modal-specs"></p>
        <p id="modal-desc" class="modal-desc"></p>
        <div class="modal-details" id="modal-details"></div>
        <div class="modal-actions">
          <a id="modal-wa" href="#" class="btn btn-primary" target="_blank" rel="noopener">Solicitar información</a>
          <a href="#contacto" class="btn btn-outline" id="modal-contact">Ir a contacto</a>
        </div>
      </div>
    </div>
  </div>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div>
        <img src="assets/img/logo.jpg" alt="Chimeneas Ecológicas" class="footer-logo">
        <p>Chimeneas de bioetanol y artículos de decoración para el hogar.</p>
        <p id="footer-address"></p>
      </div>
      <div>
        <h4>Síguenos</h4>
        <p><a id="ig-footer" href="#" target="_blank" rel="noopener">Instagram</a></p>
        <p><a id="fb-footer" href="#" target="_blank" rel="noopener">Facebook</a></p>
        <p><a id="tt-footer" href="#" target="_blank" rel="noopener">TikTok</a></p>
        <p><a id="maps-footer" href="#" target="_blank" rel="noopener">Google Maps</a></p>
      </div>
      <div>
        <h4>Contacto</h4>
        <p><a id="wa-footer" href="#" target="_blank" rel="noopener">WhatsApp</a></p>
        <p id="footer-email"></p>
      </div>
    </div>
    <div class="copyright">© <span id="year"></span> Chimeneas Ecológicas. Todos los derechos reservados. · <a href="aviso-privacidad.html">Aviso de privacidad</a></div>
  </footer>

  <a id="wa-float" href="#" class="wa-float" target="_blank" rel="noopener" aria-label="WhatsApp">💬</a>

  <!-- Popup / Ventana emergente -->
  <div id="site-popup" class="popup-overlay" aria-hidden="true">
    <div class="popup-modal" role="dialog" aria-modal="true" aria-labelledby="popup-title">
      <button class="popup-close" id="popup-close" aria-label="Cerrar">&times;</button>
      <img id="popup-img" class="popup-img" src="" alt="" style="display:none;">
      <div class="popup-body">
        <h3 id="popup-title"></h3>
        <p id="popup-text"></p>
        <div class="popup-actions">
          <a id="popup-btn" href="#" class="btn btn-primary" style="display:none;">Ver más</a>
          <button type="button" id="popup-close-btn" class="btn btn-outline">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <?php echo $customBodyEnd; ?>
  <script src="assets/js/main.js?v=21" defer></script>
</body>
</html>
