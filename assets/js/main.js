(async function(){
  // Config with defaults (fallback if fetch fails)
  let cfg = {
    images: { logo: 'assets/img/logo.jpg', hero: 'assets/img/hero.jpg', showroom: 'assets/img/showroom.jpg' },
    whatsapp: '522227150505',
    whatsappMessage: 'Hola, me interesa una cotización',
    googleMapsUrl: 'https://maps.app.goo.gl/pQSRYkWWo2Cht6Jr9',
    googleMapsEmbed: 'https://www.google.com/maps?q=Calzada+Zavaleta+2302,+Puebla,+Mexico&output=embed',
    instagram: 'https://www.instagram.com/chimeneasecologicaspuebla/',
    facebook: 'https://www.facebook.com/chimeneas.puebla',
    tiktok: 'https://www.tiktok.com/@chimeneas.ecologi',
    email: 'hola@chimeneasecologicas.com',
    address: 'Calzada Zavaleta 2302, Puebla, México',
    formsEndpoint: '/api/contact.php',
    cacheBust: ''
  };
  try {
    const res = await fetch('api/config.php?t=' + Date.now());
    const data = await res.json();
    if (data && typeof data === 'object') cfg = { ...cfg, ...data };
  } catch(e){ console.warn('config load failed', e); }

  const cb = cfg.cacheBust ? '?v=' + cfg.cacheBust : '';
  const PLACEHOLDER = 'assets/img/placeholder.jpg';
  const wa = (msg) => `https://wa.me/${cfg.whatsapp || '522220000000'}?text=${encodeURIComponent(msg || cfg.whatsappMessage || 'Hola')}`;
  const prodImages = (p) => {
    const imgs = (Array.isArray(p.images) && p.images.length) ? p.images : (p.image ? [p.image] : []);
    return imgs.length ? imgs : [PLACEHOLDER];
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const setHref = (id, url) => { const el = document.getElementById(id); if (el && url) el.href = url; };
  const setSrc = (id, url) => { const el = document.getElementById(id); if (el && url) el.src = url + cb; };

  const imgs = cfg.images || {};
  setSrc('img-logo', imgs.logo);
  setSrc('img-showroom', imgs.showroom);
  const heroBg = document.getElementById('hero-bg');
  if (heroBg && imgs.hero) heroBg.style.backgroundImage = `url('${imgs.hero}${cb}')`;

  setHref('wa-header', wa(cfg.whatsappMessage));
  setHref('wa-hero', wa('Hola, me interesa una cotización'));
  const waShowroom = document.getElementById('wa-showroom');
  if (waShowroom) waShowroom.href = `https://wa.me/${cfg.whatsapp}`;
  setHref('wa-visit', wa('Hola, quiero agendar una visita al showroom'));
  setHref('wa-catalog', wa('Hola, me interesa recibir el catálogo'));
  setHref('wa-contact', wa('Hola, me interesa una cotización'));
  setHref('wa-float', wa(cfg.whatsappMessage));
  setHref('wa-footer', wa(cfg.whatsappMessage));
  setHref('wa-gracias', wa(cfg.whatsappMessage));
  setHref('maps-link', cfg.googleMapsUrl);
  setHref('maps-footer', cfg.googleMapsUrl);
  const mapIframe = document.getElementById('map-iframe');
  if (mapIframe && cfg.googleMapsEmbed) mapIframe.src = cfg.googleMapsEmbed;
  const ig = document.getElementById('ig-link'); if (ig) ig.href = cfg.instagram;
  const fb = document.getElementById('fb-link'); if (fb) fb.href = cfg.facebook;
  const tt = document.getElementById('tt-link'); if (tt) tt.href = cfg.tiktok;
  const igf = document.getElementById('ig-footer'); if (igf) igf.href = cfg.instagram;
  const fbf = document.getElementById('fb-footer'); if (fbf) fbf.href = cfg.facebook;
  const ttf = document.getElementById('tt-footer'); if (ttf) ttf.href = cfg.tiktok;
  const addr = document.getElementById('footer-address'); if (addr) addr.textContent = cfg.address;
  const email = document.getElementById('footer-email'); if (email) email.textContent = cfg.email;
  const year = document.getElementById('year'); if (year) year.textContent = new Date().getFullYear();

  // Google Analytics 4 (dynamic)
  if (cfg.analyticsId && cfg.analyticsId !== 'G-XXXXXXXXXX') {
    const s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + cfg.analyticsId;
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    window.gtag = gtag;
    gtag('js', new Date());
    gtag('config', cfg.analyticsId);
  }

  // Popup / ventana emergente (configurable desde el admin)
  (function initPopup(){
    const el = document.getElementById('site-popup');
    if (!el) return;
    if (String(cfg.popup_enabled || '') !== '1') return;

    const title = String(cfg.popup_title || '').trim();
    const body = String(cfg.popup_body || '').trim();
    const image = String(cfg.popup_image || '').trim();
    if (!title && !body && !image) return;

    // Clave de cookie basada en el contenido: si cambia el aviso, se vuelve a mostrar
    const key = 'chm_popup_' + String(title + '|' + body).replace(/[^a-z0-9]/gi, '').slice(0, 40).toLowerCase();
    const once = String(cfg.popup_once || '') === '1';
    if (once && document.cookie.indexOf(key + '=1') !== -1) return;

    const titleEl = document.getElementById('popup-title');
    const textEl = document.getElementById('popup-text');
    const imgEl = document.getElementById('popup-img');
    const btn = document.getElementById('popup-btn');

    if (titleEl) { titleEl.textContent = title; titleEl.style.display = title ? '' : 'none'; }
    if (textEl) { textEl.textContent = body; textEl.style.display = body ? '' : 'none'; }
    if (imgEl) {
      if (image) { imgEl.src = image; imgEl.alt = title || 'Aviso'; imgEl.style.display = ''; }
      else { imgEl.removeAttribute('src'); imgEl.style.display = 'none'; }
    }
    const btnText = String(cfg.popup_btn_text || '').trim();
    let btnUrl = String(cfg.popup_btn_url || '').trim();
    // Solo permitir enlaces seguros (relativos, ancla, http/https, mailto, tel)
    if (btnUrl && !/^(#|\/|\.|https?:\/\/|mailto:|tel:)/i.test(btnUrl)) btnUrl = '';
    if (btn) {
      if (btnText && btnUrl) {
        btn.textContent = btnText;
        btn.href = btnUrl;
        btn.style.display = '';
      } else {
        btn.style.display = 'none';
      }
    }

    function closePopup() {
      el.classList.remove('open');
      el.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (once) {
        const d = new Date();
        d.setTime(d.getTime() + 30 * 24 * 60 * 60 * 1000);
        document.cookie = key + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
      }
    }

    function openPopup() {
      el.classList.add('open');
      el.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    const closeBtn = document.getElementById('popup-close');
    const closeBtn2 = document.getElementById('popup-close-btn');
    if (closeBtn) closeBtn.addEventListener('click', closePopup);
    if (closeBtn2) closeBtn2.addEventListener('click', closePopup);
    if (btn) btn.addEventListener('click', closePopup);
    el.addEventListener('click', (e) => { if (e.target === el) closePopup(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el.classList.contains('open')) closePopup(); });

    // Pequeña espera para no competir con la carga inicial
    setTimeout(openPopup, 800);
  })();

  // Mobile nav
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.nav');
  if (toggle && nav) toggle.addEventListener('click', () => nav.classList.toggle('is-open'));

  // Form handling: envío nativo (navegación) para pasar el reto anti-bot del hosting.
  // Muestra el error devuelto por el servidor al redirigir de vuelta al formulario.
  const form = document.getElementById('contact-form');
  const msg = document.getElementById('form-msg');
  if (form) {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') === 'error') {
      const errMsg = urlParams.get('msg');
      if (msg && errMsg) {
        msg.textContent = decodeURIComponent(errMsg);
        msg.style.color = '#c62828';
      }
      const contacto = document.getElementById('contacto');
      if (contacto) contacto.scrollIntoView();
      // limpia la URL para no repetir el mensaje al recargar
      if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', window.location.pathname);
      }
    }
  }

  // CAPTCHA matemático
  const captchaRefresh = document.getElementById('captcha-refresh');
  async function loadCaptcha() {
    try {
      const res = await fetch('api/captcha.php');
      const c = await res.json();
      document.getElementById('captcha-a').value = c.a;
      document.getElementById('captcha-b').value = c.b;
      document.getElementById('captcha-token').value = c.token;
      document.getElementById('captcha-time').value = c.time;
      document.getElementById('captcha-question').textContent = `¿Cuánto es ${c.a} + ${c.b}?`;
      document.getElementById('captcha-answer').value = '';
    } catch(e) {
      document.getElementById('captcha-question').textContent = 'Verificación no disponible';
    }
  }
  if (captchaRefresh) {
    loadCaptcha();
    captchaRefresh.addEventListener('click', loadCaptcha);
  }

  // Toggle interés (cita vs solo información)
  const interestInputs = document.querySelectorAll('input[name="interest"]');
  const dateGroup = document.getElementById('date-group');
  const dateInput = document.getElementById('appointment-date');
  function toggleDate() {
    const checked = document.querySelector('input[name="interest"]:checked');
    const isCita = checked && checked.value === 'cita';
    if (dateGroup) dateGroup.style.display = isCita ? '' : 'none';
    if (dateInput) {
      if (isCita) dateInput.setAttribute('required', '');
      else dateInput.removeAttribute('required');
    }
  }
  interestInputs.forEach(r => r.addEventListener('change', toggleDate));
  if (dateInput) dateInput.min = new Date().toISOString().split('T')[0];
  toggleDate();

  // Modal
  const modal = document.getElementById('product-modal');
  const modalClose = document.getElementById('modal-close');
  if (modal) {
    if (modalClose) modalClose.addEventListener('click', () => modal.classList.remove('open'));
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('open'); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') modal.classList.remove('open'); });
    const modalContact = document.getElementById('modal-contact');
    if (modalContact) modalContact.addEventListener('click', () => modal.classList.remove('open'));
  }

  // Render products
  const grid = document.getElementById('products-grid');
  if (grid) {
    try {
      const res = await fetch('api/products.php');
      const products = await res.json();
      grid.innerHTML = products.map(p => {
        const img = p.image || PLACEHOLDER;
        const priceNorm = String(p.price || '').toLowerCase().replace(/[^0-9a-z\s]/g, '');
        const searchText = esc(`${p.name} ${p.category} ${p.specs} ${p.price} ${priceNorm}`).toLowerCase();
        const priceMatch = String(p.price || '').replace(/,/g, '').match(/\d+(\.\d+)?/);
        const priceNum = priceMatch ? parseFloat(priceMatch[0]) : 0;
        return `
        <article class="product-card" data-search="${searchText}" data-category="${esc(p.category)}" data-price="${priceNum}" data-id="${p.id}">
          <img src="${img}${cb}" alt="${p.name}" loading="lazy">
          <div class="pc-body">
            <span class="pc-cat">${p.category}</span>
            <h3>${p.name}</h3>
            ${p.price ? `<div class="pc-price">${p.price}</div>` : ''}
            <p class="pc-specs">${p.specs}</p>
            <button class="btn btn-outline btn-sm open-modal" data-id="${p.id}">Ver detalle</button>
          </div>
        </article>
      `;}).join('');
      window.__products = products;
      // Poblar el listado de productos del formulario
      const sel = document.getElementById('product-select');
      if (sel) {
        sel.innerHTML = '<option value="">Selecciona un producto…</option>';
        products.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.name;
          opt.textContent = p.name;
          sel.appendChild(opt);
        });
      }
      // Poblar el selector de categorías (filtro)
      const catSel = document.getElementById('category-filter');
      if (catSel) {
        const cats = [];
        products.forEach(p => {
          if (p.category && cats.indexOf(p.category) === -1) cats.push(p.category);
        });
        cats.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c;
          opt.textContent = c;
          catSel.appendChild(opt);
        });
      }
      // Marquee
      const track = document.getElementById('marquee-track');
      if (track) {
        const imgs = products.map(p => `<img src="${p.image || PLACEHOLDER}${cb}" alt="${p.name}" loading="lazy">`).join('');
        track.innerHTML = imgs + imgs;
      }
      document.querySelectorAll('.open-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const id = parseInt(btn.dataset.id);
          const p = (window.__products || []).find(x => x.id === id);
          if (!p) return;
          // Gallery
          const gal = document.getElementById('modal-gallery');
          const imgs = prodImages(p);
          gal.innerHTML = imgs.map(src => `<img src="${src}${cb}" alt="${p.name}" loading="lazy">`).join('');
          if (imgs.length <= 1) gal.classList.add('single'); else gal.classList.remove('single');
          document.getElementById('modal-cat').textContent = p.category;
          document.getElementById('modal-title').textContent = p.name;
          const priceEl = document.getElementById('modal-price');
          priceEl.textContent = p.price || '';
          priceEl.style.display = p.price ? '' : 'none';
          document.getElementById('modal-specs').textContent = p.specs;
          document.getElementById('modal-desc').textContent = p.description || '';
          const detailsEl = document.getElementById('modal-details');
          detailsEl.textContent = p.details || '';
          detailsEl.style.display = p.details ? '' : 'none';
          const waMsg = `Hola, me interesa información sobre ${p.name}${p.price ? ' (' + p.price + ')' : ''}`;
          document.getElementById('modal-wa').href = `https://wa.me/${cfg.whatsapp}?text=${encodeURIComponent(waMsg)}`;
          modal.classList.add('open');
        });
      });
      // Búsqueda / filtro / ordenamiento del catálogo
      const searchInput = document.getElementById('product-search');
      const searchClear = document.getElementById('search-clear');
      const categorySel = document.getElementById('category-filter');
      const sortSel = document.getElementById('sort-filter');
      if (searchInput) {
        const updateCatalog = () => {
          // 1) Ordenar (mueve las tarjetas en el DOM)
          if (sortSel && sortSel.value !== 'default') {
            const cards = Array.from(grid.querySelectorAll('.product-card'));
            cards.sort((a, b) => {
              const pa = parseFloat(a.getAttribute('data-price') || '0');
              const pb = parseFloat(b.getAttribute('data-price') || '0');
              return sortSel.value === 'price-asc' ? pa - pb : pb - pa;
            });
            cards.forEach(c => grid.appendChild(c));
          }
          // 2) Filtrar (texto + categoría)
          const q = searchInput.value.trim().toLowerCase();
          const cat = categorySel ? categorySel.value : '';
          const catNorm = cat.toLowerCase();
          const cards = grid.querySelectorAll('.product-card');
          const total = cards.length;
          let visible = 0;
          cards.forEach(card => {
            const text = (card.getAttribute('data-search') || '').toLowerCase();
            const textMatch = !q || text.indexOf(q) !== -1;
            const catMatch = !cat || (card.getAttribute('data-category') || '').toLowerCase() === catNorm;
            const show = textMatch && catMatch;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
          });
          // 3) Contador de resultados
          if (searchClear) searchClear.style.display = q ? '' : 'none';
          const countEl = document.getElementById('catalog-count');
          if (countEl) {
            countEl.textContent = (q || cat) ? ('Mostrando ' + visible + ' de ' + total + ' productos') : (total + ' productos');
          }
          // 4) Mensaje si no hay resultados
          let empty = document.getElementById('catalog-empty');
          if (visible === 0) {
            if (!empty) {
              empty = document.createElement('p');
              empty.id = 'catalog-empty';
              empty.textContent = 'No se encontraron productos.';
              grid.parentNode.appendChild(empty);
            }
            empty.style.display = '';
          } else if (empty) {
            empty.style.display = 'none';
          }
        };
        searchInput.addEventListener('input', updateCatalog);
        if (categorySel) categorySel.addEventListener('change', updateCatalog);
        if (sortSel) sortSel.addEventListener('change', updateCatalog);
        if (searchClear) {
          searchClear.addEventListener('click', () => {
            searchInput.value = '';
            updateCatalog();
            searchInput.focus();
          });
        }
        // Inicializa el contador
        updateCatalog();
      }
    } catch(e) {
      console.warn('products load failed', e);
      grid.innerHTML = '<p style="text-align:center;color:#6b5a52;">No se pudieron cargar los productos.</p>';
    }
  }

  // Progressive enhancement with GSAP (non-blocking, content stays visible)
  if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
    gsap.registerPlugin(ScrollTrigger);

    // Hero entrance (only if hero is in view)
    gsap.from('.hero-title', { opacity: 0, y: 60, duration: 1, ease: 'power3.out', delay: 0.2 });
    gsap.from('.hero-sub', { opacity: 0, y: 30, duration: 0.8, ease: 'power2.out', delay: 0.5 });
    gsap.from('.hero-actions > *', { opacity: 0, y: 20, duration: 0.6, stagger: 0.12, ease: 'power2.out', delay: 0.7 });

    // Parallax hero bg
    gsap.to('.hero-bg', { yPercent: 20, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });

    // WhatsApp pulse
    gsap.to('.wa-float', { scale: 1.08, duration: 1.2, repeat: -1, yoyo: true, ease: 'sine.inOut' });

    // Counters (animate from 0, final value already in HTML as fallback)
    document.querySelectorAll('.stat-num').forEach((el) => {
      const target = parseInt(el.dataset.count, 10) || 0;
      const obj = { val: 0 };
      gsap.to(obj, { val: target, duration: 2, ease: 'power2.out', scrollTrigger: { trigger: el, start: 'top 85%' }, onUpdate: () => { el.textContent = Math.floor(obj.val); } });
    });

    // Magnetic buttons
    document.querySelectorAll('.btn').forEach((btn) => {
      btn.addEventListener('mousemove', (e) => {
        const r = btn.getBoundingClientRect();
        const x = e.clientX - r.left - r.width / 2;
        const y = e.clientY - r.top - r.height / 2;
        btn.style.transform = `translate(${x * 0.2}px, ${y * 0.2}px)`;
      });
      btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
    });
  }
})();
