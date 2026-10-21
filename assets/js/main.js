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
  const wa = (msg) => `https://wa.me/${cfg.whatsapp || '522220000000'}?text=${encodeURIComponent(msg || cfg.whatsappMessage || 'Hola')}`;

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

  // Mobile nav
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.nav');
  if (toggle && nav) toggle.addEventListener('click', () => nav.classList.toggle('is-open'));

  // Form handling
  const form = document.getElementById('contact-form');
  const msg = document.getElementById('form-msg');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      const data = new FormData(form);
      try {
        const res = await fetch(cfg.formsEndpoint || '/api/contact.php', { method: 'POST', body: data });
        const json = await res.json();
        if (msg) {
          msg.textContent = json.message || (json.success ? 'Enviado' : 'Error');
          msg.style.color = json.success ? '#2e7d32' : '#c62828';
        }
        if (json.success) {
          form.reset();
          setTimeout(() => { window.location.href = 'gracias.html'; }, 1200);
        }
      } catch(err) {
        if (msg) { msg.textContent = 'Error de red.'; msg.style.color = '#c62828'; }
      }
      btn.disabled = false;
    });
  }

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
      grid.innerHTML = products.map(p => `
        <article class="product-card">
          <img src="${p.image}${cb}" alt="${p.name}" loading="lazy">
          <div class="pc-body">
            <span class="pc-cat">${p.category}</span>
            <h3>${p.name}</h3>
            <p>${p.specs}</p>
            <button class="btn btn-outline btn-sm open-modal" data-id="${p.id}">Ver detalle</button>
          </div>
        </article>
      `).join('');
      window.__products = products;
      document.querySelectorAll('.open-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const id = parseInt(btn.dataset.id);
          const p = (window.__products || []).find(x => x.id === id);
          if (!p) return;
          document.getElementById('modal-img').src = p.image + cb;
          document.getElementById('modal-img').alt = p.name;
          document.getElementById('modal-cat').textContent = p.category;
          document.getElementById('modal-title').textContent = p.name;
          document.getElementById('modal-specs').textContent = p.specs;
          document.getElementById('modal-desc').textContent = p.description || '';
          const waMsg = `Hola, me interesa información sobre ${p.name}`;
          document.getElementById('modal-wa').href = `https://wa.me/${cfg.whatsapp}?text=${encodeURIComponent(waMsg)}`;
          modal.classList.add('open');
        });
      });
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
