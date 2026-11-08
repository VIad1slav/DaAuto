/* =========================================================
   DaAuto — script
   ========================================================= */

/* Куда отправлять заявки: send.php пересылает их в Telegram
   (настройка — в README.md). Пусто — форма только показывает «Спасибо». */
const FORM_ENDPOINT = 'send.php';
const SENT_MS = 2000; // сколько показывать «Спасибо» после отправки

/* ---------- Польский перевод (русский берётся из HTML) ---------- */
const PL = {
  'meta.title': 'DaAuto — samochody z Europy pod klucz w Polsce',
  'meta.desc': 'Zakup samochodów od europejskich dealerów, transport po Europie, przegląd techniczny, rejestracja w Warszawie i ubezpieczenie. DaAuto — wszystko dla Twojego auta w Polsce.',

  'nav.services': 'Usługi',
  'nav.europe': 'Auta z UE',
  'nav.prices': 'Cennik',
  'nav.delivery': 'Transport',
  'nav.insurance': 'Ubezpieczenia',
  'nav.contact': 'Kontakt',
  'cta.request': 'Zostaw zgłoszenie',

  'hero.badge': 'Samochody z Europy pod klucz · Polska',
  'hero.title': 'Samochód Twoich marzeń <span class="grad">od europejskiego dealera</span>',
  'hero.lead': 'Wybierzemy, sprowadzimy, zarejestrujemy i ubezpieczymy. Zrozumiały samochód z przejrzystą historią — w cenie z dolnej granicy polskiego rynku.',
  'hero.cta1': 'Sprawdź cenę pod klucz',
  'hero.cta2': 'Nasze usługi',
  'hero.chip1': 'Niemcy',
  'hero.chip2': 'Holandia',
  'hero.chip3': 'Belgia',
  'hero.chip4': 'cała Europa',

  'form.title': 'Dowiedz się, ile będzie kosztować auto pod klucz',
  'form.sub': 'Odpowiemy w ciągu dnia roboczego',
  'form.name': 'Imię',
  'form.namePh': 'Jan',
  'form.phone': 'Telefon',
  'form.service': 'Usługa',
  'form.lang': 'Język kontaktu',
  'form.msg': 'Komentarz',
  'form.msgPh': 'Marka, model, budżet, termin…',
  'form.submit': 'Wyślij zgłoszenie',
  'form.consent': 'Wysyłając formularz, wyrażasz zgodę na przetwarzanie danych osobowych i kontakt zwrotny.',
  'form.ok': 'Dziękujemy! Skontaktujemy się z Tobą wkrótce.',
  'opt.europe': 'Zakup auta od europejskiego dealera',
  'opt.selection': 'Wyszukiwanie auta w Polsce',
  'opt.inspection': 'Przegląd techniczny',
  'opt.registration': 'Rejestracja i tablice (Warszawa)',
  'opt.delivery': 'Transport po Europie',
  'opt.insurance': 'Ubezpieczenie',

  'stat1': 'aut sprowadzono do Polski z innych krajów UE w 2025 roku',
  'stat2': 'samochodów droższych niż 50 000 zł zarejestrowanych w Polsce pochodzi z innego kraju UE',
  'stat3': 'tyle razy niemiecki rynek aut jest większy od polskiego',

  'mq.1': 'Zakup od dealerów z UE',
  'mq.2': 'Transport lawetą',
  'mq.3': 'Przegląd techniczny',
  'mq.4': 'Rejestracja w Warszawie',
  'mq.5': 'Ubezpieczenie',
  'mq.6': 'Wsparcie w Twoim języku',

  'svc.kicker': 'Czym się zajmujemy',
  'svc.title': 'Nasze usługi',
  'svc.sub': 'Wszystko, czego potrzebuje samochód w Polsce — od zakupu w Europie po tablice i ubezpieczenie',
  's1.t': 'Zakup aut od europejskich dealerów',
  's1.d': 'Niemcy, Holandia, Belgia i cała Europa — pod klucz',
  's2.t': 'Wyszukiwanie auta w Polsce',
  's2.d': 'Kontakt do sprawdzonego specjalisty',
  's3.t': 'Przegląd techniczny',
  's3.d': 'Nasza usługa — 250 zł',
  's4.t': 'Rejestracja i tablice',
  's4.d': 'Tylko Warszawa · od 400 zł',
  's5.t': 'Transport po Europie',
  's5.d': 'Wszystkie kierunki, ubezpieczone lawety',
  's6.t': 'Ubezpieczenie auta',
  's6.d': 'Multiagent 6 największych ubezpieczycieli w Polsce',

  'eu.kicker': 'Zakup samochodów od europejskich dealerów',
  'eu.title': 'Kilka liczb',
  'eu.p1': 'Według statystyk za 2025 rok co drugi samochód o wartości powyżej 50 000 zł zarejestrowany w Polsce został sprowadzony z innego kraju UE. Przede wszystkim z Niemiec, Holandii i Belgii. Łącznie sprowadzono ponad 900 000 aut.',
  'eu.p2': 'Oznacza to, że sami Polacy rozważają auta z Europy z nie mniejszym zainteresowaniem niż lokalne. Sam rynek niemiecki jest 7 razy większy od polskiego.',
  'eu.p3': 'Nawet po uwzględnieniu kosztów transportu, polskiej akcyzy i naszych usług cena zrozumiałego samochodu z przejrzystą historią będzie na dole widełek polskiego rynku, a może nawet z pewnym rabatem. Wszystko zależy od konkretnego auta.',
  'eu.p4': 'Z przyjemnością wybierzemy dla Ciebie samochód od europejskiego dealera. Zostaw zgłoszenie i dowiedz się, ile będzie kosztować auto Twoich marzeń pod klucz.',
  'eu.f.label': 'Z czego składa się cena pod klucz',
  'eu.f.car': 'Samochód',
  'eu.f.carD': 'od europejskiego dealera',
  'eu.f.del': 'Transport',
  'eu.f.delD': 'lawetą do Polski',
  'eu.f.tax': 'Akcyza',
  'eu.f.taxD': 'polska',
  'eu.f.svc': 'Nasze usługi',
  'eu.f.svcD': 'pełna obsługa',
  'eu.f.res': 'Cena z dołu polskiego rynku',
  'eu.f.resD': 'a czasem nawet z rabatem',
  'eu.m1': 'aut powyżej 50 000 zł — z UE',
  'eu.m2': 'sprowadzono w 2025 roku',
  'eu.m3': 'rynek Niemiec vs Polski',

  'why.kicker': 'Zalety',
  'why.title': 'Dlaczego DaAuto',
  'w1.t': 'Przejrzysta historia',
  'w1.d': 'Zrozumiałe samochody od europejskich dealerów — bez niespodzianek po zakupie.',
  'w2.t': 'Cena z dołu rynku',
  'w2.d': 'Nawet z transportem, akcyzą i naszymi usługami.',
  'w3.t': 'W Twoim języku',
  'w3.d': 'Mówimy po rosyjsku i po polsku, chodzimy za Ciebie do urzędu i pomagamy w trudnych sytuacjach.',
  'w4.t': 'Wszystko w jednym miejscu',
  'w4.d': 'Zakup, transport, przegląd, rejestracja i ubezpieczenie — jeden zespół.',

  'sel.kicker': 'Wyszukiwanie auta w Polsce',
  'sel.title': 'Wymagania wobec auta',
  'sel.r1b': '≤ 10 lat',
  'sel.r1': 'samochody nie starsze niż 10 lat',
  'sel.r2': 'nie tańsze niż 40 000 zł',
  'sel.p1': 'Tak naprawdę sami nie zajmujemy się wyszukiwaniem aut, ale przekażemy Ci kontakt do specjalisty, któremu ufamy tak samo jak sobie.',
  'sel.p2': 'A jako alternatywę zaproponujemy oszczędność — zakup samochodu na jednej z europejskich aukcji.',
  'sel.o1tag': 'Opcja 1',
  'sel.o1': 'Sprawdzony specjalista',
  'sel.o1d': 'Kontakt do osoby, której ufamy jak sobie',
  'sel.o2tag': 'Opcja 2 · oszczędność',
  'sel.o2': 'Aukcja europejska',
  'sel.o2d': 'Zakup samochodu na jednej z aukcji w UE',
  'sel.cta': 'Otrzymaj kontakt',

  'pr.kicker': 'Stałe ceny',
  'pr.title': 'Przegląd i rejestracja',
  'pr.sub': 'Przejrzyste koszty bez ukrytych opłat',
  'pr.t.title': 'Przegląd techniczny',
  'pr.t.big': 'nasza usługa',
  'pr.t.r1': 'Nasza usługa',
  'pr.t.r2': 'Sam przegląd',
  'pr.t.r2v': 'wg cennika stacji',
  'pr.t.r3': 'Ponowny przyjazd w razie wykrycia usterek',
  'pr.cta': 'Umów przegląd',
  'pr.r.flag': 'Tylko Warszawa',
  'pr.r.title': 'Rejestracja i tablice',
  'pr.r.if': 'Jeśli:',
  'pr.r.c1': 'cenisz swój czas',
  'pr.r.c2': 'nie masz pewności co do swojego polskiego',
  'pr.r.c3': 'masz wątpliwości, czy komplet dokumentów jest pełny',
  'pr.r.p': '— zleć nam rejestrację samochodu. Przygotujemy pełnomocnictwo i pójdziemy za Ciebie do urzędu. Pomożemy też wcześniej sprawdzić, czy masz wszystkie dokumenty i czy są poprawnie wypełnione.',
  'pr.r.r1': 'Rejestracja samochodu',
  'pr.r.r2': 'Rejestracja (tablice) + opłacenie polskiej akcyzy',
  'pr.cta2': 'Zleć rejestrację',

  'd.kicker': 'Transport samochodów po Europie',
  'd.title': 'Wszystkie kierunki',
  'd.dir1': 'UE → Polska',
  'd.dir2': 'Polska → Europa',
  'd.p1': 'Jeśli chcesz sprowadzić auto z dowolnego kraju UE do Polski albo odwrotnie — przewieźć swoje auto lawetą z Polski do Europy, żeby nie nabijać przebiegu — zgłoś się do nas.',
  'd.f1': 'Jesteśmy podłączeni do giełd transportowych',
  'd.f1d': 'Widzimy aktualne oferty przewoźników w całej Europie',
  'd.f2': 'Wielu przewoźników znamy osobiście',
  'd.f2d': 'Widzimy ich opinie i to, jak długo są na rynku',
  'd.f3': 'Rozumiemy wycenę',
  'd.f3d': 'Wiemy, z czego składa się koszt przewozu',
  'd.ps': '<b>P.S.</b> Wszystkie lawety są ubezpieczone. Pilnujemy tego.',
  'd.cta': 'Wyceń transport',
  'city.ams': 'Amsterdam',
  'city.bru': 'Bruksela',
  'city.ber': 'Berlin',
  'city.mun': 'Monachium',
  'city.par': 'Paryż',
  'city.mil': 'Mediolan',
  'city.waw': 'Warszawa',

  'i.kicker': 'Ubezpieczenie auta',
  'i.title': 'Dobierzemy dla Ciebie najlepszą ofertę',
  'i.lead': 'Jeden z naszych pracowników jest multiagentem sześciu największych towarzystw ubezpieczeniowych w Polsce.',
  'i.p': 'Jeśli kupujesz swój pierwszy samochód w Polsce, prawdopodobnie nie wiesz, że stawki za to samo auto w różnych towarzystwach mogą różnić się kilkukrotnie. Sami widzieliśmy przypadki, gdy różnica w cenie polisy była ponad 10-krotna.',
  'i.n1': 'największych ubezpieczycieli w Polsce',
  'i.n2': 'różnicy w cenie za to samo auto',
  'i.know': 'Co warto wiedzieć',
  'i.k1': 'Cena polisy u niezależnego agenta i w biurze ubezpieczyciela jest dokładnie taka sama — we wszystkich towarzystwach.',
  'i.k2': 'Otrzymasz wsparcie w trudnych sytuacjach w swoim języku.',
  'i.k3': 'Pomożemy uzyskać maksymalne odszkodowanie w razie szkody — czego raczej nie można oczekiwać od konsultantów infolinii samych ubezpieczycieli.',
  'i.k4': 'miłe bonusy',
  'i.cta': 'Dobierz ubezpieczenie',

  'c.kicker': 'Kontakt',
  'c.title': 'Zostaw zgłoszenie',
  'c.sub': 'Napisz, jakiego auta lub usługi potrzebujesz — obliczymy koszt i skontaktujemy się z Tobą.',
  'c.phone': 'Telefon',
  'c.city': 'Adres',
  'c.cityV': 'ul. Landwarowska 2, Warszawa',
  'map.label': 'Nasz adres',
  'map.city': '03-107 Warszawa, Polska',
  'map.route': 'Wyznacz trasę',
  'map.open': 'Otwórz w Mapach Google',

  'f.tag': 'Samochody z Europy pod klucz',
  'f.privacy': 'Polityka prywatności'
};

(function () {
  const doc = document.documentElement;
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  /* ---------- marquee: удваиваем ленту, чтобы на широких мониторах не было пустоты справа ---------- */
  const track = document.querySelector('.marquee__track');
  if (track) track.innerHTML += track.innerHTML;

  /* ---------- i18n ---------- */
  const RU = { text: {}, ph: {}, content: {} };
  $$('[data-i18n]').forEach(el => { RU.text[el.dataset.i18n] = el.innerHTML; });
  $$('[data-i18n-ph]').forEach(el => { RU.ph[el.dataset.i18nPh] = el.getAttribute('placeholder'); });
  $$('[data-i18n-content]').forEach(el => { RU.content[el.dataset.i18nContent] = el.getAttribute('content'); });

  const store = {
    get() { try { return localStorage.getItem('daauto-lang'); } catch (e) { return null; } },
    set(v) { try { localStorage.setItem('daauto-lang', v); } catch (e) {} }
  };

  function applyLang(lang) {
    const pl = lang === 'pl';
    $$('[data-i18n]').forEach(el => {
      const k = el.dataset.i18n;
      const v = pl ? PL[k] : RU.text[k];
      if (v != null) el.innerHTML = v;
    });
    $$('[data-i18n-ph]').forEach(el => {
      const k = el.dataset.i18nPh;
      const v = pl ? PL[k] : RU.ph[k];
      if (v != null) el.setAttribute('placeholder', v);
    });
    $$('[data-i18n-content]').forEach(el => {
      const k = el.dataset.i18nContent;
      const v = pl ? PL[k] : RU.content[k];
      if (v != null) el.setAttribute('content', v);
    });
    doc.lang = lang;
    // язык подписей на карте
    $$('.map__frame').forEach(f => {
      const src = f.getAttribute('src').replace(/([?&]hl=)[a-z]+/, `$1${lang}`);
      if (src !== f.getAttribute('src')) f.setAttribute('src', src);
    });
    $$('.lang button').forEach(b => b.classList.toggle('is-active', b.dataset.lang === lang));
    $$('select[name="language"]').forEach(s => { s.value = lang; });
    store.set(lang);
    const url = new URL(location.href);
    url.searchParams.set('lang', lang);
    history.replaceState(null, '', url);
  }

  const qp = new URLSearchParams(location.search).get('lang');
  const initial = (qp === 'pl' || qp === 'ru') ? qp
    : store.get() || ((navigator.language || '').toLowerCase().startsWith('pl') ? 'pl' : 'ru');
  if (initial !== 'ru') applyLang(initial);
  $$('.lang button').forEach(b => b.addEventListener('click', () => applyLang(b.dataset.lang)));

  /* ---------- header, progress, fab ---------- */
  const header = document.querySelector('.header');
  const bar = document.querySelector('.progress span');
  const fab = document.querySelector('.fab');
  const hero = document.querySelector('.hero');
  const contact = document.getElementById('contact');
  let ticking = false;
  function onScroll() {
    const y = window.scrollY;
    header.classList.toggle('is-scrolled', y > 30);
    const h = doc.scrollHeight - innerHeight;
    bar.style.transform = `scaleX(${h > 0 ? y / h : 0})`;
    const pastHero = y > hero.offsetHeight - 200;
    const atContact = contact.getBoundingClientRect().top < innerHeight * .8;
    fab.classList.toggle('is-visible', pastHero && !atContact);
    ticking = false;
  }
  addEventListener('scroll', () => { if (!ticking) { requestAnimationFrame(onScroll); ticking = true; } }, { passive: true });
  onScroll();

  /* ---------- mobile menu ---------- */
  const burger = document.querySelector('.burger');
  const nav = document.getElementById('nav');
  function setMenu(open) {
    burger.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
    header.classList.toggle('menu-open', open);
  }
  burger.addEventListener('click', () => setMenu(burger.getAttribute('aria-expanded') !== 'true'));
  $$('a', nav).forEach(a => a.addEventListener('click', () => setMenu(false)));

  /* ---------- preselect service from CTA ---------- */
  const contactService = document.getElementById('contactService');
  $$('[data-service]').forEach(a => a.addEventListener('click', () => {
    if (contactService) contactService.value = a.dataset.service;
  }));

  /* ---------- reveal ---------- */
  const reveals = $$('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        const el = e.target;
        el.classList.add('in');
        io.unobserve(el);
        // после появления снимаем reveal, чтобы hover-анимации работали без задержки
        setTimeout(() => { el.classList.remove('reveal', 'in'); el.style.removeProperty('--d'); }, 1500);
      });
    }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
    // stagger siblings
    reveals.forEach(el => {
      const sibs = Array.from(el.parentElement.children).filter(c => c.classList.contains('reveal'));
      const i = sibs.indexOf(el);
      if (i > 0) el.style.setProperty('--d', `${Math.min(i, 6) * 0.08}s`);
      io.observe(el);
    });
  } else {
    reveals.forEach(el => el.classList.add('in'));
  }

  /* ---------- counters ---------- */
  const fmt = n => n.toLocaleString(doc.lang === 'pl' ? 'pl-PL' : 'ru-RU');
  function animateCount(el) {
    const target = +el.dataset.count;
    const dur = 1800;
    const t0 = performance.now();
    (function step(t) {
      const p = Math.min((t - t0) / dur, 1);
      const eased = 1 - Math.pow(1 - p, 4);
      el.textContent = fmt(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(step);
    })(t0);
  }
  const counters = $$('[data-count]');
  if ('IntersectionObserver' in window) {
    const co = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        animateCount(e.target);
        co.unobserve(e.target);
      });
    }, { threshold: .5 });
    counters.forEach(c => co.observe(c));
  } else {
    counters.forEach(c => { c.textContent = fmt(+c.dataset.count); });
  }

  /* ---------- active nav link ---------- */
  const links = $$('.nav a');
  const sections = links.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);
  if ('IntersectionObserver' in window) {
    const so = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        links.forEach(a => a.classList.toggle('is-active', a.getAttribute('href') === '#' + e.target.id));
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    sections.forEach(s => so.observe(s));
  }

  /* ---------- forms ---------- */
  $$('[data-form]').forEach(form => {
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      let ok = true;
      $$('input[required]', form).forEach(inp => {
        const field = inp.closest('.field');
        const bad = inp.name === 'phone'
          ? inp.value.replace(/\D/g, '').length < 9
          : inp.value.trim().length < 2;
        field.classList.toggle('is-error', bad);
        if (bad) ok = false;
      });
      if (!ok) { form.querySelector('.is-error input').focus(); return; }

      const data = Object.fromEntries(new FormData(form).entries());
      data.page_lang = doc.lang;
      const btn = form.querySelector('[type="submit"]');
      btn.disabled = true;
      try {
        if (FORM_ENDPOINT) {
          const res = await fetch(FORM_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data)
          });
          const out = await res.json().catch(() => ({}));
          if (!res.ok || !out.ok) throw new Error(out.error || res.status);
        }
        form.classList.add('is-sent');
        // через 2 секунды прячем «Спасибо» и показываем пустую форму
        clearTimeout(form._sentTimer);
        form._sentTimer = setTimeout(() => form.classList.remove('is-sent'), SENT_MS);
        form.reset();
        $$('select[name="language"]', form).forEach(s => { s.value = doc.lang; });
      } catch (e) {
        console.warn('DaAuto form:', e);
        if (e.message === 'rate_limit') {
          alert(doc.lang === 'pl'
            ? 'Zgłoszenie zostało już wysłane — skontaktujemy się z Tobą. Kolejne możesz wysłać za 10 minut albo zadzwoń: +48 000 000 000.'
            : 'Заявка уже отправлена — мы свяжемся с вами. Следующую можно отправить через 10 минут или позвоните: +48 000 000 000.');
          return;
        }
        alert(doc.lang === 'pl'
          ? 'Nie udało się wysłać zgłoszenia. Spróbuj ponownie albo zadzwoń: +48 000 000 000.'
          : 'Не удалось отправить заявку. Попробуйте ещё раз или позвоните: +48 000 000 000.');
      } finally {
        btn.disabled = false;
      }
    });
    $$('input', form).forEach(inp => inp.addEventListener('input', () => inp.closest('.field').classList.remove('is-error')));
  });

  /* ---------- year ---------- */
  const y = document.getElementById('year');
  if (y) y.textContent = new Date().getFullYear();
})();
