const projects = {
  urban: {
    name: 'Hanok Urban 84',
    area: 84,
    basePrice: 5200000,
    term: '4–5 месяцев'
  },
  family: {
    name: 'Seoul Family 132',
    area: 132,
    basePrice: 8650000,
    term: '6–7 месяцев'
  },
  residence: {
    name: 'Busan Signature 186',
    area: 186,
    basePrice: 12900000,
    term: '8–10 месяцев'
  }
};

const regions = {
  center: {
    label: 'Центральный регион',
    coefficient: 1.08,
    cities: ['Москва', 'Тула', 'Калуга']
  },
  south: {
    label: 'Южный регион',
    coefficient: 1.0,
    cities: ['Краснодар', 'Сочи', 'Новороссийск']
  },
  northWest: {
    label: 'Северо-Запад',
    coefficient: 1.12,
    cities: ['Санкт-Петербург', 'Выборг', 'Псков']
  },
  east: {
    label: 'Дальний Восток',
    coefficient: 1.18,
    cities: ['Владивосток', 'Хабаровск', 'Южно-Сахалинск']
  }
};

const packages = {
  base: { label: 'Essential', multiplier: 1 },
  comfort: { label: 'Comfort', multiplier: 1.14 },
  premium: { label: 'Signature', multiplier: 1.28 }
};

const extras = {
  terrace: { label: 'Терраса', price: 450000 },
  garage: { label: 'Гараж', price: 700000 },
  smart: { label: 'Умный дом', price: 250000 },
  design: { label: 'Интерьерный пакет', price: 180000 }
};

const modalDraftKey = 'hanzip-application-draft';
const modalHistoryHash = '#application';
let csrfToken = '';
let currentPage = 0;

const projectSelect = document.getElementById('projectSelect');
const regionSelect = document.getElementById('regionSelect');
const citySelect = document.getElementById('citySelect');
const packageSelect = document.getElementById('packageSelect');
const calculatorForm = document.getElementById('calculatorForm');
const resultPrice = document.getElementById('resultPrice');
const summaryProject = document.getElementById('summaryProject');
const summaryRegion = document.getElementById('summaryRegion');
const summaryCity = document.getElementById('summaryCity');
const summaryArea = document.getElementById('summaryArea');
const summaryPackage = document.getElementById('summaryPackage');
const summaryTerm = document.getElementById('summaryTerm');
const resultNote = document.getElementById('resultNote');
const modal = document.getElementById('applicationModal');
const form = document.getElementById('applicationForm');
const formStatus = document.getElementById('formStatus');
const sliderTrack = document.getElementById('sliderTrack');
const sliderPager = document.getElementById('sliderPager');
const prevSlide = document.getElementById('prevSlide');
const nextSlide = document.getElementById('nextSlide');

function formatCurrency(value) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0
  }).format(value);
}

function fillProjectSelect() {
  Object.entries(projects).forEach(([key, project]) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = `${project.name} · ${project.area} м²`;
    projectSelect.append(option);
  });
}

function fillRegionSelect() {
  Object.entries(regions).forEach(([key, region]) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = region.label;
    regionSelect.append(option);
  });
}

function updateCities() {
  const region = regions[regionSelect.value];
  citySelect.innerHTML = '';
  region.cities.forEach((city) => {
    const option = document.createElement('option');
    option.value = city;
    option.textContent = city;
    citySelect.append(option);
  });
}

function getSelectedExtras() {
  return Array.from(document.querySelectorAll('input[name="extras"]:checked')).map((item) => item.value);
}

function calculateEstimate() {
  const project = projects[projectSelect.value];
  const region = regions[regionSelect.value];
  const pack = packages[packageSelect.value];
  const selectedExtras = getSelectedExtras();

  const extrasTotal = selectedExtras.reduce((sum, extra) => sum + extras[extra].price, 0);
  const total = Math.round(project.basePrice * region.coefficient * pack.multiplier + extrasTotal);

  resultPrice.textContent = formatCurrency(total);
  summaryProject.textContent = project.name;
  summaryRegion.textContent = region.label;
  summaryCity.textContent = citySelect.value;
  summaryArea.textContent = `${project.area} м²`;
  summaryPackage.textContent = pack.label;
  summaryTerm.textContent = project.term;

  const extrasText = selectedExtras.length
    ? selectedExtras.map((extra) => extras[extra].label).join(', ')
    : 'без дополнительных опций';

  resultNote.textContent = `В расчёт включены: ${extrasText}. Корректировка по региону: ${region.coefficient.toFixed(2)}.`;
}

function getSlidesPerPage() {
  if (window.innerWidth >= 960) return 3;
  if (window.innerWidth >= 700) return 2;
  return 1;
}

function updateSlider() {
  const slides = Array.from(sliderTrack.children);
  const perPage = getSlidesPerPage();
  const pages = Math.ceil(slides.length / perPage);
  currentPage = Math.max(0, Math.min(currentPage, pages - 1));
  sliderTrack.style.transform = `translateX(-${currentPage * 100}%)`;
  sliderPager.textContent = `Страница ${currentPage + 1} из ${pages}`;
}

function openModal(pushState = true) {
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
  if (pushState && location.hash !== modalHistoryHash) {
    history.pushState({ modal: true }, '', modalHistoryHash);
  }
}

function closeModal(fromPopState = false) {
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  if (!fromPopState && location.hash === modalHistoryHash) {
    history.back();
  }
}

function saveDraft() {
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.consent = form.elements.consent.checked;
  localStorage.setItem(modalDraftKey, JSON.stringify(payload));
}

function restoreDraft() {
  const raw = localStorage.getItem(modalDraftKey);
  if (!raw) return;

  try {
    const payload = JSON.parse(raw);
    Object.entries(payload).forEach(([key, value]) => {
      if (!form.elements[key]) return;
      if (form.elements[key].type === 'checkbox') {
        form.elements[key].checked = Boolean(value);
      } else {
        form.elements[key].value = value;
      }
    });
  } catch (error) {
    localStorage.removeItem(modalDraftKey);
  }
}

function validateApplication() {
  const fullname = form.elements.fullname.value.trim();
  const phone = form.elements.phone.value.trim();
  const email = form.elements.email.value.trim();

  const fullnameValid = /^[А-Яа-яЁёA-Za-z\s-]{5,150}$/.test(fullname);
  const phoneValid = /^\+?[\d\s()\-]{10,20}$/.test(phone);
  const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

  if (!fullnameValid) return 'Введите корректное ФИО.';
  if (!phoneValid) return 'Введите корректный телефон.';
  if (!emailValid) return 'Введите корректный email.';
  if (!form.elements.consent.checked) return 'Нужно согласие на обработку данных.';
  return '';
}

async function loadCsrfToken() {
  try {
    const response = await fetch('backend/csrf.php', { credentials: 'same-origin' });
    if (!response.ok) return;
    const data = await response.json();
    if (data.token) {
      csrfToken = data.token;
    }
  } catch (error) {
    csrfToken = '';
  }
}

function setFormStatus(message, type) {
  formStatus.textContent = message;
  formStatus.className = 'form-status';
  if (type) {
    formStatus.classList.add(type === 'error' ? 'is-error' : 'is-success');
  }
}

async function submitApplication(event) {
  event.preventDefault();
  setFormStatus('', '');

  const validationError = validateApplication();
  if (validationError) {
    setFormStatus(validationError, 'error');
    return;
  }

  if (!csrfToken) {
    await loadCsrfToken();
  }

  const payload = Object.fromEntries(new FormData(form).entries());
  payload.project = summaryProject.textContent;
  payload.region = summaryRegion.textContent;
  payload.city = summaryCity.textContent;
  payload.package = summaryPackage.textContent;
  payload.estimate = resultPrice.textContent;
  payload.extras = getSelectedExtras().map((key) => extras[key].label);

  try {
    const response = await fetch('backend/submit.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok) {
      const details = result.errors ? Object.values(result.errors).join(' ') : 'Не удалось отправить заявку.';
      throw new Error(details || result.message || 'Не удалось отправить заявку.');
    }

    setFormStatus(result.message || 'Заявка отправлена.', 'success');
    form.reset();
    localStorage.removeItem(modalDraftKey);
  } catch (error) {
    setFormStatus(error.message || 'Ошибка при отправке заявки.', 'error');
  }
}

function bindProjectButtons() {
  document.querySelectorAll('.js-pick-project').forEach((button) => {
    button.addEventListener('click', () => {
      projectSelect.value = button.dataset.project;
      calculateEstimate();
      document.getElementById('calculator').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
}

function bindModalButtons() {
  document.querySelectorAll('.js-open-modal').forEach((button) => {
    button.addEventListener('click', () => openModal(true));
  });

  document.querySelectorAll('.js-close-modal').forEach((button) => {
    button.addEventListener('click', () => closeModal(false));
  });
}

function initManagedImages() {
  document.querySelectorAll('.js-managed-image').forEach((image) => {
    const frame = image.closest('.is-empty, .hero-media, .media-card, .project-card__image, .result-image, .slide-media');
    if (!frame) return;

    const activate = () => frame.classList.remove('is-empty');
    const fallback = () => {
      frame.classList.add('is-empty');
      image.style.display = 'none';
    };

    if (image.complete && image.naturalWidth > 0) {
      activate();
    }

    image.addEventListener('load', activate);
    image.addEventListener('error', fallback);
  });
}

fillProjectSelect();
fillRegionSelect();
updateCities();
calculateEstimate();
updateSlider();
restoreDraft();
bindProjectButtons();
bindModalButtons();
initManagedImages();
loadCsrfToken();

document.getElementById('currentYear').textContent = new Date().getFullYear();

regionSelect.addEventListener('change', () => {
  updateCities();
  calculateEstimate();
});

[projectSelect, citySelect, packageSelect, calculatorForm].forEach((node) => {
  node.addEventListener('change', calculateEstimate);
});

prevSlide.addEventListener('click', () => {
  currentPage -= 1;
  updateSlider();
});

nextSlide.addEventListener('click', () => {
  currentPage += 1;
  updateSlider();
});

form.addEventListener('input', saveDraft);
form.addEventListener('submit', submitApplication);

window.addEventListener('resize', updateSlider);
window.addEventListener('popstate', () => {
  if (location.hash === modalHistoryHash) {
    openModal(false);
  } else if (modal.classList.contains('is-open')) {
    closeModal(true);
  }
});

if (location.hash === modalHistoryHash) {
  openModal(false);
}
