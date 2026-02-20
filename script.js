const state = {
  data: null,
  currency: "",
  activeCategory: null,
  language: "en",
};

// Initialize language and currency from localStorage with fallbacks
function initializePreferences() {
  // Load language from localStorage, fallback to "en"
  state.language = localStorage.getItem("language") || "en";
  
  // Load currency from localStorage, fallback to empty string (will be set after data loads)
  state.currency = localStorage.getItem("currency") || "";
}

// Save preferences to localStorage
function saveLanguagePreference(lang) {
  localStorage.setItem("language", lang);
}

function saveCurrencyPreference(code) {
  localStorage.setItem("currency", code);
}

const LOCALES = {
  en: {
    "header.clientArea": "Client Area",
    "nav.plans": "Plans",
    "nav.domains": "Domains",
    "nav.rates": "Exchange Rates",
    "noscript": "This website requires JS to function. Please enable JavaScript and reload this page.",
    "errors.loadFailed": "Failed to load /data.json",
    "errors.noPlans": "No plans available.",
    "errors.noProducts": "No active products in this category.",
    "errors.jsRequired": "JavaScript Required",
    "features.standard": "Standard features",
    "features.bandwidthMb": "MB",
    "features.bandwidthGb": "GB",
    "features.bandwidth": "Bandwidth",
    "features.disk": "Disk Space",
    "features.ftpAccounts": "FTP Accounts",
    "features.databases": "Databases",
    "features.emailAccounts": "Email Accounts",
    "features.subdomains": "Subdomains",
    "features.addonDomains": "Addon Domains",
    "features.cronJobs": "Cron Jobs",
    "features.inodes": "Inodes",
    "features.websites": "Websites",
    "features.ram": "RAM",
    "features.cpuCores": "CPU Cores",
    "pricing.setup": "Setup",
    "pricing.noSetupFee": "No setup fee",
    "pricing.order": "Order",
    "domains.register": "Register Domain",
    "domains.renew": "Renew",
    "domains.transfer": "Transfer",
    "domains.registrationNotAvailable": "Registration not available",
    "domains.transferNotAvailable": "Transfer not available",
    "rates.fromTo": "From / To",
    "period.1W": "Weekly",
    "period.2W": "Every 2 Weeks",
    "period.1M": "Monthly",
    "period.3M": "Quarterly",
    "period.6M": "Semiannual",
    "period.1Y": "Yearly",
    "period.2Y": "Every 2 Years",
    "period.3Y": "Every 3 Years",
    "period.ONCE": "One-time",
    "period.FREE": "Free",
  },
  fr: {
    "header.clientArea": "Espace Client",
    "nav.plans": "Plans",
    "nav.domains": "Domaines",
    "nav.rates": "Taux de Change",
    "noscript": "Ce site Web nécessite JavaScript pour fonctionner. Veuillez activer JavaScript et recharger cette page.",
    "errors.loadFailed": "Impossible de charger /data.json",
    "errors.noPlans": "Aucun plan disponible.",
    "errors.noProducts": "Aucun produit actif dans cette catégorie.",
    "errors.jsRequired": "JavaScript Requis",
    "features.standard": "Fonctionnalités standard",
    "features.bandwidthMb": "Mo",
    "features.bandwidthGb": "Go",
    "features.bandwidth": "Bande passante",
    "features.disk": "Espace disque",
    "features.ftpAccounts": "Comptes FTP",
    "features.databases": "Bases de données",
    "features.emailAccounts": "Comptes e-mail",
    "features.subdomains": "Sous-domaines",
    "features.addonDomains": "Domaines complémentaires",
    "features.cronJobs": "Tâches cron",
    "features.inodes": "Inodes",
    "features.websites": "Sites Web",
    "features.ram": "RAM",
    "features.cpuCores": "Cœurs CPU",
    "pricing.setup": "Configuration",
    "pricing.noSetupFee": "Pas de frais de configuration",
    "pricing.order": "Commande",
    "domains.register": "Enregistrer un Domaine",
    "domains.renew": "Renouveler",
    "domains.transfer": "Transfert",
    "domains.registrationNotAvailable": "Enregistrement non disponible",
    "domains.transferNotAvailable": "Transfert non disponible",
    "rates.fromTo": "De / À",
    "period.1W": "Hebdomadaire",
    "period.2W": "Tous les 2 mois",
    "period.1M": "Mensuel",
    "period.3M": "Trimestriel",
    "period.6M": "Semestriel",
    "period.1Y": "Annuel",
    "period.2Y": "Tous les 2 ans",
    "period.3Y": "Tous les 3 ans",
    "period.ONCE": "Une seule fois",
    "period.FREE": "Gratuit",
  },
  fa: {
    "header.clientArea": "پنل کاربری",
    "nav.plans": "طرح‌ها",
    "nav.domains": "دامنه‌ها",
    "nav.rates": "نرخ تبدیل ارز",
    "noscript": "این وب‌سایت نیاز به JavaScript دارد. لطفاً JavaScript را فعال کنید و صفحه را دوباره بارگذاری کنید.",
    "errors.loadFailed": "بارگذاری /data.json ناموفق بود",
    "errors.noPlans": "هیچ طرحی در دسترس نیست.",
    "errors.noProducts": "هیچ محصول فعالی در این دسته وجود ندارد.",
    "errors.jsRequired": "JavaScript الزامی است",
    "features.standard": "ویژگی‌های استاندارد",
    "features.bandwidthMb": "مگابایت",
    "features.bandwidthGb": "گیگابایت",
    "features.bandwidth": "پهنای باند",
    "features.disk": "فضای دیسک",
    "features.ftpAccounts": "حساب‌های FTP",
    "features.databases": "پایگاه‌های داده",
    "features.emailAccounts": "حساب‌های ایمیل",
    "features.subdomains": "زیردامنه‌ها",
    "features.addonDomains": "دامنه‌های اضافی",
    "features.cronJobs": "کارهای Cron",
    "features.inodes": "Inodes",
    "features.websites": "وب‌سایت‌ها",
    "features.ram": "رم",
    "features.cpuCores": "هسته‌های پردازنده",
    "pricing.setup": "نصب",
    "pricing.noSetupFee": "بدون هزینه نصب",
    "pricing.order": "سفارش",
    "domains.register": "ثبت دامنه",
    "domains.renew": "تمدید",
    "domains.transfer": "انتقال",
    "domains.registrationNotAvailable": "ثبت در دسترس نیست",
    "domains.transferNotAvailable": "انتقال در دسترس نیست",
    "rates.fromTo": "از / به",
    "period.1W": "هفتگی",
    "period.2W": "هر ۲ هفته",
    "period.1M": "ماهانه",
    "period.3M": "سه‌ماهه",
    "period.6M": "شش‌ماهه",
    "period.1Y": "سالانه",
    "period.2Y": "هر ۲ سال",
    "period.3Y": "هر ۳ سال",
    "period.ONCE": "یک‌بار",
    "period.FREE": "رایگان",
  },
};

const LANGUAGE_ICONS = {
  en: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#bd3d44" d="M0 0h512v512H0"/><path stroke="#fff" stroke-width="40" d="M0 58h512M0 137h512M0 216h512M0 295h512M0 374h512M0 453h512"/><path fill="#192f5d" d="M0 0h390v275H0z"/><marker id="us-a" markerHeight="30" markerWidth="30"><path fill="#fff" d="m15 0 9.3 28.6L0 11h30L5.7 28.6"/></marker><path fill="none" marker-mid="url(#us-a)" d="m0 0 18 11h65 65 65 65 66L51 39h65 65 65 65L18 66h65 65 65 65 66L51 94h65 65 65 65L18 121h65 65 65 65 66L51 149h65 65 65 65L18 177h65 65 65 65 66L51 205h65 65 65 65L18 232h65 65 65 65 66z"/></svg>',
  fr: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#fff" d="M0 0h512v512H0z"/><path fill="#000091" d="M0 0h170.7v512H0z"/><path fill="#e1000f" d="M341.3 0H512v512H341.3z"/></svg>',
  fa: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><defs><clipPath id="ir-a"><path fill-opacity=".7" d="M186 0h496v496H186z"/></clipPath></defs><g fill-rule="evenodd" clip-path="url(#ir-a)" transform="translate(-192)scale(1.0321)"><path fill="#fff" d="M0 0h868.1v496H0z"/><path fill="#da0000" d="M0 333.1h868.1v163H0z"/><path fill="#239f40" d="M0 0h868.1v163H0z"/></g></svg>',
};

const LANGUAGE_NAMES = {
  en: "English",
  fr: "Français",
  fa: "فارسی",
};

function getLanguageIcon(code, size = 18) {
  const icon = LANGUAGE_ICONS[code] || LANGUAGE_ICONS.en;
  return icon.replace(/viewBox="[^"]*"/g, `viewBox="0 0 512 512" width="${size}" height="${size}"`);
}

function i18n(key, lang = state.language) {
  return LOCALES[lang]?.[key] || LOCALES.en[key] || key;
}

const CURRENCY_ICONS = {
  USD: '<svg viewBox="0 0 24 24" width="18" height="18" style="fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;"><path d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>',
  EUR: '<svg viewBox="0 0 24 24" width="18" height="18" style="fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;"><path d="M14.25 7.756a4.5 4.5 0 1 0 0 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>',
  DEFAULT: '<svg viewBox="0 0 24 24" width="18" height="18" style="fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;"><path d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"></path></svg>'
};

function getCurrencyIcon(code, size = 18) {
  const icon = CURRENCY_ICONS[code] || CURRENCY_ICONS.DEFAULT;
  return icon.replace(/width="18"/g, `width="${size}"`).replace(/height="18"/g, `height="${size}"`);
}

function byId(id) {
  return document.getElementById(id);
}

function text(value, fallback = "") {
  if (value === null || value === undefined) return fallback;
  return String(value).trim();
}

function isTruthy(value) {
  if (typeof value === "boolean") return value;
  const raw = text(value).toLowerCase();
  return ["1", "true", "yes", "on", "enabled", "active"].includes(raw);
}

function localHrefOrFallback(raw, fallback = "#") {
  const value = text(raw);
  if (!value) return fallback;
  if (value.startsWith("/")) return value;
  return fallback;
}

function periodLabel(period) {
  const key = text(period).toUpperCase();
  return i18n(`period.${key}`, state.language) || key || "Period";
}

function formatMoney(price, currencyCode) {
  const amount = text(price, "0");
  const currency = (state.data?.currencies || []).find((c) => c.code === currencyCode);
  if (!currency) return `${amount} ${currencyCode}`.trim();

  const pattern = text(currency.format);
  if (pattern.includes("{{price}}")) return pattern.replace("{{price}}", amount);
  if (pattern.includes("{price}")) return pattern.replace("{price}", amount);
  if (currency.sign) return `${currency.sign}${amount}`;
  return `${amount} ${currencyCode}`.trim();
}

function chooseProductPrice(product, currency, preferredPeriod) {
  const pricing = product?.pricing || {};
  const model = pricing[currency] || pricing[Object.keys(pricing)[0]];
  if (!model) return { amount: "N/A", periodLabel: "Unavailable", setup: "" };

  const recurrent = model.recurrent || {};
  const recurrentPeriods = Object.keys(recurrent);

  let selectedPeriod = "";
  let selectedEntry = null;

  if (preferredPeriod && recurrent[preferredPeriod] && isTruthy(recurrent[preferredPeriod].enabled ?? true)) {
    selectedPeriod = preferredPeriod;
    selectedEntry = recurrent[preferredPeriod];
  }

  if (!selectedEntry) {
    selectedPeriod = recurrentPeriods.find((p) => isTruthy(recurrent[p].enabled ?? true)) || "";
    selectedEntry = selectedPeriod ? recurrent[selectedPeriod] : null;
  }

  if (!selectedEntry && model.once && isTruthy(model.once.enabled ?? true)) {
    selectedPeriod = "ONCE";
    selectedEntry = model.once;
  }

  if (!selectedEntry && model.free) {
    selectedPeriod = "FREE";
    selectedEntry = model.free;
  }

  if (!selectedEntry) return { amount: "N/A", periodLabel: "Unavailable", setup: "" };

  const setupRaw = text(selectedEntry.setup);
  const setup = setupRaw && setupRaw !== "0" && setupRaw !== "0.00"
    ? `${i18n("pricing.setup", state.language)}: ${formatMoney(setupRaw, currency)}`
    : i18n("pricing.noSetupFee", state.language);

  return {
    amount: formatMoney(selectedEntry.price ?? "0", currency),
    periodLabel: periodLabel(selectedPeriod),
    setup,
  };
}

function availablePeriods(data, currency) {
  const all = new Set();
  for (const product of data.products || []) {
    const model = product?.pricing?.[currency];
    if (!model || !model.recurrent) continue;

    for (const [period, row] of Object.entries(model.recurrent)) {
      if (isTruthy(row?.enabled ?? true)) all.add(period.toUpperCase());
    }
  }

  const rank = ["1W", "2W", "1M", "3M", "6M", "1Y", "2Y", "3Y"];
  return [...all].sort((a, b) => {
    const ia = rank.indexOf(a);
    const ib = rank.indexOf(b);
    if (ia === -1 && ib === -1) return a.localeCompare(b);
    if (ia === -1) return 1;
    if (ib === -1) return -1;
    return ia - ib;
  });
}

function empty(message) {
  const el = document.createElement("div");
  el.className = "empty";
  el.textContent = message;
  return el;
}

function renderCategoryFilters() {
  const container = byId("categoryFilters");
  const categories = state.data?.categories || [];
  
  container.innerHTML = "";
  
  if (!categories.length) return;
  
  // Set first category as default if not set
  if (state.activeCategory === null) {
    state.activeCategory = String(categories[0].id);
  }
  
  // Individual category buttons
  for (const category of categories) {
    const btn = document.createElement("button");
    btn.className = "category-btn";
    btn.dataset.categoryId = category.id;
    
    // Add icon if available
    if (text(category.icon_url)) {
      const img = document.createElement("img");
      img.src = text(category.icon_url);
      img.alt = "";
      img.className = "category-btn-icon";
      btn.appendChild(img);
    }
    
    // Add title text
    const span = document.createElement("span");
    span.textContent = text(category.title);
    btn.appendChild(span);
    
    // Set active state
    if (String(category.id) === String(state.activeCategory)) {
      btn.classList.add("active");
    }
    
    btn.onclick = () => {
      state.activeCategory = String(category.id);
      updateCategoryButtons();
      renderProducts();
    };
    container.appendChild(btn);
  }
}

function updateCategoryButtons() {
  const buttons = document.querySelectorAll(".category-btn");
  buttons.forEach(btn => {
    const btnCategoryId = btn.dataset.categoryId;
    if (btnCategoryId === null || btnCategoryId === "null") {
      // All Plans button
      btn.classList.toggle("active", state.activeCategory === null);
    } else {
      // Category button
      btn.classList.toggle("active", String(state.activeCategory) === btnCategoryId);
    }
  });
}

function productCard(product) {
  const card = document.createElement("article");
  card.className = "card";

  const titleWrap = document.createElement("div");
  titleWrap.className = "card-title-wrap";

  if (text(product.icon_url)) {
    const img = document.createElement("img");
    img.src = text(product.icon_url);
    img.alt = "";
    img.className = "card-icon";
    titleWrap.appendChild(img);
  }

  const h3 = document.createElement("h3");
  h3.textContent = text(product.title, "Plan");
  titleWrap.appendChild(h3);
  card.appendChild(titleWrap);

  const desc = document.createElement("p");
  desc.className = "desc";
  desc.textContent = text(product.description, "No description.");

  // Get available periods for this product and sort them
  const pricing = product?.pricing?.[state.currency] || {};
  const recurrent = pricing.recurrent || {};
  let availablePeriods = Object.keys(recurrent).filter(p => isTruthy(recurrent[p]?.enabled ?? true));
  
  // Sort periods by defined order
  const periodOrder = ["1W", "2W", "1M", "3M", "6M", "1Y", "2Y", "3Y"];
  availablePeriods.sort((a, b) => {
    const ia = periodOrder.indexOf(a.toUpperCase());
    const ib = periodOrder.indexOf(b.toUpperCase());
    if (ia === -1 && ib === -1) return a.localeCompare(b);
    if (ia === -1) return 1;
    if (ib === -1) return -1;
    return ia - ib;
  });
  
  // Pricing list (all billing periods)
  const pricingList = document.createElement("div");
  pricingList.className = "pricing-list";
  
  for (const period of availablePeriods) {
    const priceInfo = chooseProductPrice(product, state.currency, period);
    const noSetupFeeText = i18n("pricing.noSetupFee", state.language);
    const setupText = priceInfo.setup !== noSetupFeeText ? ` + ${priceInfo.setup}` : "";
    
    const row = document.createElement("div");
    row.className = "pricing-row";
    row.innerHTML = `
      <span class="price-left">${priceInfo.amount}</span>
      <span class="period-right">/ ${priceInfo.periodLabel}${setupText}</span>
    `;
    pricingList.appendChild(row);
  }
  
  const price = document.createElement("div");
  price.className = "pricing-section";
  price.appendChild(pricingList);

  const features = document.createElement("div");
  features.className = "features-list";
  const featureList = (product.features || []).filter(f => f && f.key && f.value);

  const getFeatureLabel = (key) => {
    const labels = {
      "bandwidth": "features.bandwidth",
      "disk": "features.disk",
      "ftp_accounts": "features.ftpAccounts",
      "databases": "features.databases",
      "email_accounts": "features.emailAccounts",
      "subdomains": "features.subdomains",
      "addon_domains": "features.addonDomains",
      "cron_jobs": "features.cronJobs",
      "inodes": "features.inodes",
      "websites": "features.websites",
      "ram": "features.ram",
      "cpu_cores": "features.cpuCores",
    };
    return i18n(labels[key], state.language) || key.replace(/_/g, " ");
  };

  const getSvgIcon = (key) => {
    const icons = {
      "bandwidth": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"/></svg>',
      "disk": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>',
      "ftp_accounts": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 1 0-2.636 6.364M16.5 12V8.25"/></svg>',
      "databases": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>',
      "email_accounts": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 1 0-2.636 6.364M16.5 12V8.25"/></svg>',
      "subdomains": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>',
      "addon_domains": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>',
      "cron_jobs": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
      "inodes": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z"/></svg>',
      "websites": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/></svg>',
      "ram": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>',
      "cpu_cores": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z"/></svg>',
    };
    return icons[key] || null;
  };

  const formatFeatureValue = (key, value) => {
    const val = parseInt(value, 10);
    if (key === "disk" || key === "bandwidth") {
      return val >= 1000 ? Math.round(val / 1000) + " " + i18n("features.bandwidthGb", state.language) : val + " " + i18n("features.bandwidthMb", state.language);
    }
    return value;
  };

  if (!featureList.length) {
    const empty = document.createElement("div");
    empty.className = "feature-row empty";
    empty.textContent = i18n("features.standard", state.language);
    features.appendChild(empty);
  } else {
    for (const feature of featureList) {
      const row = document.createElement("div");
      row.className = "feature-row";
      
      const label = document.createElement("span");
      label.className = "feature-label";
      const svg = getSvgIcon(feature.key);
      const labelText = getFeatureLabel(feature.key);
      if (svg) {
        label.innerHTML = svg + " " + labelText;
      } else {
        label.textContent = labelText;
      }
      
      const value = document.createElement("span");
      value.className = "feature-value";
      value.textContent = formatFeatureValue(feature.key, feature.value);
      
      row.appendChild(label);
      row.appendChild(value);
      features.appendChild(row);
    }
  }

  const actions = document.createElement("div");
  actions.className = "actions";

  const order = document.createElement("a");
  order.className = "btn primary btn--flex";
  const orderUrl = text(product.order_url);
  order.href = orderUrl || "#";
  order.target = orderUrl && !orderUrl.startsWith("/") ? "_blank" : "_self";
  order.innerHTML = `
    <svg viewBox="0 0 24 24" width="18" height="18" style="stroke-width: 2; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round;">
      <circle cx="9" cy="21" r="1"></circle>
      <circle cx="20" cy="21" r="1"></circle>
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
    </svg>
    ${i18n("pricing.order", state.language)}
  `;

  actions.appendChild(order);
  card.append(desc, features, price, actions);
  return card;
  }

function renderProducts() {
  const root = byId("categoriesContainer");
  root.innerHTML = "";

  let categories = state.data?.categories || [];
  const productsById = new Map((state.data?.products || []).map((p) => [String(p.id), p]));

  // Set first category as default if not set
  if (!state.activeCategory && categories.length > 0) {
    state.activeCategory = String(categories[0].id);
  }

  // Filter by active category if set
  if (state.activeCategory) {
    categories = categories.filter(c => String(c.id) === String(state.activeCategory));
  }

  if (!categories.length) {
    root.appendChild(empty(i18n("errors.noPlans", state.language)));
    return;
  }

  for (const category of categories) {
    const section = document.createElement("section");
    section.className = "category";

    const head = document.createElement("div");
    head.className = "category-head";

    if (text(category.icon_url)) {
      const img = document.createElement("img");
      img.src = text(category.icon_url);
      img.alt = "";
      img.loading = "lazy";
      head.appendChild(img);
    }

    const title = document.createElement("h3");
    title.textContent = text(category.title, "Category");
    head.appendChild(title);

    const cards = document.createElement("div");
    cards.className = "cards";

    for (const id of category.products || []) {
      const product = productsById.get(String(id));
      if (!product) continue;
      cards.appendChild(productCard(product));
    }

    if (!cards.children.length) cards.appendChild(empty(i18n("errors.noProducts", state.language)));

    section.append(head, cards);
    root.appendChild(section);
  }
}

function renderDomains() {
  const root = byId("domainsContainer");
  const section = byId("domainsSection");
  root.innerHTML = "";
  
  // Filter only enabled domains
  const domains = (state.data?.domains || []).filter(d => isTruthy(d.enabled));

  if (!domains.length) {
    section.style.display = "none";
    return;
  }

  section.style.display = "block";

  const wrap = document.createElement("div");
  wrap.className = "table-wrap";

  const table = document.createElement("table");
  table.className = "table domains-table";
  table.innerHTML = `
    <thead>
      <tr>
        <th>TLD</th>
        <th data-i18n="domains.register">Register</th>
        <th data-i18n="domains.renew">Renew</th>
        <th data-i18n="domains.transfer">Transfer</th>
      </tr>
    </thead>
    <tbody></tbody>
  `;

  const infoIcon = `<svg class="info-icon" viewBox="0 0 24 24" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>`;

  const tbody = table.querySelector("tbody");
  for (const domain of domains) {
    const p = domain.pricing?.[state.currency] || domain.pricing?.[Object.keys(domain.pricing || {})[0]] || {};
    const canRegister = isTruthy(domain.allow_register);
    const canTransfer = isTruthy(domain.allow_transfer);
    
    const tr = document.createElement("tr");
    
    // TLD cell
    const tdTld = document.createElement("td");
    tdTld.className = "tld-cell";
    tdTld.textContent = text(domain.tld, "-");
    tr.appendChild(tdTld);
    
    // Register cell
    const tdRegister = document.createElement("td");
    tdRegister.className = canRegister ? "" : "price-disabled";
    tdRegister.innerHTML = formatMoney(p.register ?? "-", state.currency);
    if (!canRegister) {
      tdRegister.innerHTML += infoIcon;
      tdRegister.dataset.tooltip = i18n("domains.registrationNotAvailable", state.language);
    }
    tr.appendChild(tdRegister);
    
    // Renew cell (always available if domain is enabled)
    const tdRenew = document.createElement("td");
    tdRenew.textContent = formatMoney(p.renew ?? "-", state.currency);
    tr.appendChild(tdRenew);
    
    // Transfer cell
    const tdTransfer = document.createElement("td");
    tdTransfer.className = canTransfer ? "" : "price-disabled";
    tdTransfer.innerHTML = formatMoney(p.transfer ?? "-", state.currency);
    if (!canTransfer) {
      tdTransfer.innerHTML += infoIcon;
      tdTransfer.dataset.tooltip = i18n("domains.transferNotAvailable", state.language);
    }
    tr.appendChild(tdTransfer);
    
    tbody.appendChild(tr);
  }

  wrap.appendChild(table);
  root.appendChild(wrap);
  
  // Add domain registration button if product slug exists
  const domainRegSlug = state.data?.domain_registration_slug;
  if (domainRegSlug) {
    const btnContainer = document.createElement("div");
    btnContainer.className = "btn-container";
    
    const btn = document.createElement("a");
    btn.className = "btn primary btn--flex";
    const baseUrl = text(state.data?.branding?.clientarea_url);
    btn.href = baseUrl ? `${baseUrl}/order/${domainRegSlug}` : "#";
    btn.target = "_blank";
    btn.innerHTML = `
      <svg viewBox="0 0 24 24" width="18" height="18" style="stroke-width: 2; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round;">
        <circle cx="9" cy="21" r="1"></circle>
        <circle cx="20" cy="21" r="1"></circle>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
      </svg>
      ${i18n("domains.register", state.language)}
    `;
    
    btnContainer.appendChild(btn);
    root.appendChild(btnContainer);
  }
    }

    function renderRates() {
  const root = byId("ratesContainer");
  const section = byId("ratesSection");
  root.innerHTML = "";

  const relations = state.data?.currency_rates?.relations || {};
  const codes = Object.keys(relations);

  // Only show rates if more than one currency
  if (!codes.length || codes.length < 2) {
    section.style.display = "none";
    return;
  }

  section.style.display = "block";

  const wrap = document.createElement("div");
  wrap.className = "table-wrap";

  const table = document.createElement("table");
  table.className = "table";

  const head = document.createElement("thead");
  const hr = document.createElement("tr");
  hr.appendChild(Object.assign(document.createElement("th"), { textContent: i18n("rates.fromTo", state.language) }));
  for (const to of codes) hr.appendChild(Object.assign(document.createElement("th"), { textContent: to }));
  head.appendChild(hr);

  const body = document.createElement("tbody");
  for (const from of codes) {
    const tr = document.createElement("tr");
    tr.appendChild(Object.assign(document.createElement("td"), { textContent: from }));
    for (const to of codes) {
      const td = document.createElement("td");
      td.textContent = text(relations[from]?.[to], "-");
      tr.appendChild(td);
    }
    body.appendChild(tr);
  }

  table.append(head, body);
  wrap.appendChild(table);
  root.appendChild(wrap);
}

function updateControls() {
  const currencies = state.data?.currencies || [];
  const currencyBtn = byId("currencyBtn");
  const currencyDropdown = byId("currencyDropdown");
  const currencyDisplay = byId("currencyDisplay");

  // Hide button if only 1 currency
  if (currencies.length <= 1) {
    currencyBtn.style.display = "none";
    if (currencies.length === 1) {
      state.currency = currencies[0].code;
      saveCurrencyPreference(state.currency);
    }
    return;
  }

  currencyBtn.style.display = "flex";

  // Set default currency (if still not set)
  if (!state.currency) {
    state.currency = text(state.data?.meta?.default_currency) ||
      currencies.find((c) => isTruthy(c.is_default))?.code ||
      currencies[0]?.code || "";
    if (state.currency) {
      saveCurrencyPreference(state.currency);
    }
  }

  currencyDisplay.textContent = state.currency;
  byId("currencyIcon").innerHTML = getCurrencyIcon(state.currency);

  // Build dropdown items
  currencyDropdown.innerHTML = "";
  for (const currency of currencies) {
    const item = document.createElement("button");
    item.className = "currency-item" + (currency.code === state.currency ? " active" : "");
    item.type = "button";
    item.innerHTML = `
      ${getCurrencyIcon(currency.code, 16)}
      <span class="currency-code">${currency.code}</span>
      <svg class="check" viewBox="0 0 24 24" width="14" height="14" style="fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    `;
    item.addEventListener("click", () => selectCurrency(currency.code));
    currencyDropdown.appendChild(item);
  }
}

function selectCurrency(code) {
  state.currency = code;
  saveCurrencyPreference(code);
  byId("currencyDisplay").textContent = code;
  byId("currencyIcon").innerHTML = getCurrencyIcon(code);
  closeCurrencyDropdown();
  
  // Update active states
  byId("currencyDropdown").querySelectorAll(".currency-item").forEach(item => {
    item.classList.toggle("active", item.querySelector(".currency-code")?.textContent === code);
  });
  
  renderProducts();
  renderDomains();
  renderRates();
}

function openCurrencyDropdown() {
  const btn = byId("currencyBtn");
  const dropdown = byId("currencyDropdown");
  
  btn.classList.add("open");
  dropdown.classList.add("open");
  
  // Match dropdown width to the trigger button
  const rect = btn.getBoundingClientRect();
  dropdown.style.width = rect.width + "px";
  dropdown.style.top = (rect.bottom + 6) + "px";
  dropdown.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8)) + "px";
}

function closeCurrencyDropdown() {
  byId("currencyBtn").classList.remove("open");
  byId("currencyDropdown").classList.remove("open");
}

function toggleCurrencyDropdown() {
  const dropdown = byId("currencyDropdown");
  if (dropdown.classList.contains("open")) {
    closeCurrencyDropdown();
  } else {
    openCurrencyDropdown();
  }
}

function getAssetUrl(key) {
  const customAssets = state.data?.meta?.custom_assets || {};
  const brandingAssets = state.data?.branding?.assets || {};
  return text(customAssets[key]) || text(brandingAssets[key]);
}

function renderFooter() {
  const container = byId("footerContainer");
  container.innerHTML = "";
  const brand = state.data?.branding || {};
  const motto = text(brand.motto);
  const footerContent = text(brand.footer_content);
  
  // Build footer HTML
  let footerHTML = "";
  if (motto) {
    footerHTML += `<p class="footer-motto">${motto}</p>`;
  }
  if (footerContent) {
    footerHTML += footerContent;
  }
  
  container.innerHTML = footerHTML;
}

function applyBranding() {
  const brand = state.data?.branding || {};
  const meta = state.data?.meta || {};
  const companyName = text(brand.company?.name);
  const motto = text(brand.motto);
  const brandMark = text(brand.brand_mark);
  const clientareaUrl = text(brand.clientarea_url);
  
  // Update page title and meta description
  document.title = companyName;
  const metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) {
    metaDesc.content = `${companyName} - ${motto}`;
  }
  
  // Apply branding text
  byId("brandMark").textContent = brandMark;
  
  // Set up Client Area button
  const clientAreaBtn = byId("clientAreaBtn");
  if (clientareaUrl) {
    clientAreaBtn.href = clientareaUrl;
    clientAreaBtn.style.display = "inline-flex";
  } else {
    clientAreaBtn.style.display = "none";
  }
  
  // Get asset URLs (custom_assets override branding.assets)
  const logoUrl = getAssetUrl("logo_url");
  const logoDarkUrl = getAssetUrl("logo_dark_url");
  const faviconUrl = getAssetUrl("favicon_url");
  
  // Update favicon
  if (faviconUrl) {
    let faviconEl = document.querySelector('link[rel="icon"]');
    if (!faviconEl) {
      faviconEl = document.createElement("link");
      faviconEl.rel = "icon";
      document.head.appendChild(faviconEl);
    }
    faviconEl.href = faviconUrl;
  }
  
  // Update brand logos (light and dark mode - CSS handles visibility)
  const lightLogoEl = byId("brandLogoLight");
  const darkLogoEl = byId("brandLogoDark");
  
  if (lightLogoEl && logoUrl) {
    lightLogoEl.src = logoUrl;
    lightLogoEl.alt = companyName;
    lightLogoEl.removeAttribute("style");
  }
  
  if (darkLogoEl) {
    const darkLogo = logoDarkUrl || logoUrl;
    if (darkLogo) {
      darkLogoEl.src = darkLogo;
      darkLogoEl.alt = companyName;
      darkLogoEl.removeAttribute("style");
    }
  }
  
  // Apply header/footer background images
  const headerEl = document.querySelector("header");
  if (headerEl) {
    const headerBgUrl = getAssetUrl("header_bg_url");
    if (headerBgUrl) {
      headerEl.style.backgroundImage = `url('${headerBgUrl}')`;
      headerEl.style.backgroundSize = "cover";
      headerEl.style.backgroundPosition = "center";
    }
  }
  
  const footerEl = document.querySelector("footer");
  if (footerEl) {
    const footerBgUrl = getAssetUrl("footer_bg_url");
    if (footerBgUrl) {
      footerEl.style.backgroundImage = `url('${footerBgUrl}')`;
      footerEl.style.backgroundSize = "cover";
      footerEl.style.backgroundPosition = "center";
    }
  }
  

}

function renderAll() {
  applyBranding();
  renderCategoryFilters();
  renderProducts();
  renderDomains();
  renderRates();
  renderFooter();
}

// Language dropdown functions
function updateLanguageDropdown() {
  const languageBtn = byId("languageBtn");
  const languageDropdown = byId("languageDropdown");
  
  languageDropdown.innerHTML = "";
  for (const [code, name] of Object.entries(LANGUAGE_NAMES)) {
    const item = document.createElement("button");
    item.className = "language-item" + (code === state.language ? " active" : "");
    item.type = "button";
    item.innerHTML = `
      <span class="language-flag">${getLanguageIcon(code, 16)}</span>
      <span class="language-name">${name}</span>
      <svg class="check" viewBox="0 0 24 24" width="14" height="14" style="fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    `;
    item.addEventListener("click", () => selectLanguage(code));
    languageDropdown.appendChild(item);
  }
}

function selectLanguage(code) {
  state.language = code;
  saveLanguagePreference(code);
  document.documentElement.dir = code === "fa" ? "rtl" : "ltr";
  byId("languageDisplay").textContent = LANGUAGE_NAMES[code];
  byId("languageFlag").innerHTML = getLanguageIcon(code, 18);
  closeLanguageDropdown();
  
  // Update active states
  byId("languageDropdown").querySelectorAll(".language-item").forEach(item => {
    item.classList.toggle("active", item.querySelector(".language-name")?.textContent === LANGUAGE_NAMES[code]);
  });
  
  // Retranslate all content
  updateLanguageContent();
  renderAll();
}

function updateLanguageContent() {
  // Update all elements with data-i18n attributes
  document.querySelectorAll("[data-i18n]").forEach(el => {
    const key = el.dataset.i18n;
    el.textContent = i18n(key, state.language);
  });
  
  // Update specific header content
  const clientAreaBtn = byId("clientAreaBtn");
  if (clientAreaBtn) {
    clientAreaBtn.innerHTML = `
      <svg viewBox="0 0 24 24" width="18" height="18" style="fill: none; stroke: currentColor; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;">
        <path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"></path>
      </svg>
      ${i18n("header.clientArea", state.language)}
    `;
  }
}

function openLanguageDropdown() {
  const btn = byId("languageBtn");
  const dropdown = byId("languageDropdown");
  
  btn.classList.add("open");
  dropdown.classList.add("open");
  
  // Match dropdown width to the trigger button
  const rect = btn.getBoundingClientRect();
  dropdown.style.width = rect.width + "px";
  dropdown.style.top = (rect.bottom + 6) + "px";
  dropdown.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8)) + "px";
}

function closeLanguageDropdown() {
  byId("languageBtn").classList.remove("open");
  byId("languageDropdown").classList.remove("open");
}

function toggleLanguageDropdown() {
  const dropdown = byId("languageDropdown");
  if (dropdown.classList.contains("open")) {
    closeLanguageDropdown();
  } else {
    openLanguageDropdown();
  }
}

// Currency & Language dropdown handlers
document.addEventListener("click", (e) => {
  const currencyBtn = byId("currencyBtn");
  const currencyDropdown = byId("currencyDropdown");
  const languageBtn = byId("languageBtn");
  const languageDropdown = byId("languageDropdown");
  
  if (currencyBtn && currencyBtn.contains(e.target)) {
    e.preventDefault();
    closeLanguageDropdown();
    toggleCurrencyDropdown();
  } else if (currencyDropdown && !currencyDropdown.contains(e.target)) {
    closeCurrencyDropdown();
  }
  
  if (languageBtn && languageBtn.contains(e.target)) {
    e.preventDefault();
    closeCurrencyDropdown();
    toggleLanguageDropdown();
  } else if (languageDropdown && !languageDropdown.contains(e.target)) {
    closeLanguageDropdown();
  }
});

// Close on escape key
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeCurrencyDropdown();
    closeLanguageDropdown();
  }
});

async function boot() {
   try {
     // Initialize preferences from localStorage before anything else
     initializePreferences();
     
     // Update language display immediately
     byId("languageDisplay").textContent = LANGUAGE_NAMES[state.language] || "English";
     
     const response = await fetch("/data.json", { cache: "no-store" });
     if (!response.ok) throw new Error(`HTTP ${response.status}`);

     state.data = await response.json();
     
     // Set initial RTL/LTR
     document.documentElement.dir = state.language === "fa" ? "rtl" : "ltr";
     
     // Initialize language flag icon
     byId("languageFlag").innerHTML = getLanguageIcon(state.language, 18);
    
    // Handle currency: if not in localStorage, try to set default from data
    if (!state.currency) {
      const currencies = state.data?.currencies || [];
      if (currencies.length > 0) {
        state.currency = 
          (state.data?.meta?.default_currency) ||
          currencies.find((c) => text(c.is_default))?.code ||
          currencies[0]?.code || 
          "USD";
      }
    }
    
    updateLanguageDropdown();
    updateControls();
    updateLanguageContent();
    renderAll();
  } catch (error) {
    const metaLine = byId("metaLine");
    metaLine.textContent = i18n("errors.loadFailed", state.language);
    metaLine.classList.add("error");
    byId("categoriesContainer").appendChild(empty(`Error: ${error.message}`));
  }
}

boot();
