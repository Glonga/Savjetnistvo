// ES6 module: Portal tabs + REST fetch (vanilla JS)
// - Switches tabs via [data-tab] / [data-panel]
// - Fetches /wp-json/sv/v1/me and /wp-json/sv/v1/projects
// - Renders mini projects (max 3) and full list
// - Helpers: renderEmpty, renderError

// Global from wp_localize_script: SV = { nonce, rest, userId }
const NONCE = SV?.nonce;
const API   = SV?.rest;  // npr. ".../wp-json/sv/v1/"

function apiUrl(path){
  const base = String(API || '/wp-json/sv/v1/').replace(/\/+$/, '');
  const p = String(path || '').replace(/^\/+/, '');
  return `${base}/${p}`;
}

// Localized datetime formatter (hr-HR)
const formatDateTime = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d)) return String(iso);
  try {
    return d.toLocaleString('hr-HR', { dateStyle: 'medium', timeStyle: 'short' });
  } catch (_) {
    return d.toLocaleString();
  }
};


// Helpers
export function renderEmpty(el, message = 'Nema projekata.') {
  if (!el) return;
  el.innerHTML = '';
  el.textContent = message;
}

export function renderError(el, message = 'Greška pri učitavanju.') {
  if (!el) return;
  el.innerHTML = '';
  el.textContent = message;
}

// Selected project state
let SELECTED_PROJECT_ID = null;

export function renderProjectsList(container, projects) {
  if (!container) return;
  if (!Array.isArray(projects) || projects.length === 0) {
    renderEmpty(container, 'Nema projekata.');
    return;
  }
  container.innerHTML = '';
  const wrap = document.createElement('div');
  wrap.className = 'projects-list';

  projects.forEach(p => {
    const card = document.createElement('div');
    card.className = 'project-card';
    card.dataset.projectId = String(p.id ?? p.ID ?? '');
    const title = getTitle(p);
    const program = getProgram(p);
    const dates = getDateRange(p);
    const bits = [title];
    if (program) bits.push(program);
    if (dates) bits.push(dates);
    card.textContent = bits.join(' — ');

    // Fetch nearest upcoming meeting for this project and append a small line
    const pid = p.id ?? p.ID;
    if (pid) {
      apiGet(`meetings?${new URLSearchParams({ project_id: String(pid) }).toString()}`)
        .then((meetings) => {
          let nearest = null;
          const now = Date.now();
          for (const m of (meetings || [])){
            const t = Date.parse(m?.meeting_at || '');
            if (!isNaN(t) && t > now){
              if (!nearest || t < Date.parse(nearest.meeting_at || '')) nearest = m;
            }
          }
          const info = document.createElement('div');
          info.className = 'text-xs';
          info.textContent = 'Sljedeći susret: ' + (nearest ? formatDateTime(nearest.meeting_at) : '—');
          card.appendChild(info);
        })
        .catch(() => {
          const info = document.createElement('div');
          info.className = 'text-xs';
          info.textContent = 'Sljedeći susret: —';
          card.appendChild(info);
        });
    }

    card.addEventListener('click', () => {
      SELECTED_PROJECT_ID = Number(card.dataset.projectId || 0) || null;
      // toggle active class in list
      wrap.querySelectorAll('.project-card.active').forEach(el => el.classList.remove('active'));
      card.classList.add('active');
      // Load selected project's data and switch to Meetings tab
      loadProjectData(SELECTED_PROJECT_ID, p);
    });

    wrap.appendChild(card);
  });

  container.appendChild(wrap);
}

// Build project detail view (title, back button, meetings + payments)
export function renderProjectDetail(projectId, project) {
  const meetingsPanel = document.querySelector('[data-panel="meetings"]');
  if (!meetingsPanel) return;
  meetingsPanel.innerHTML = '';

  const back = document.createElement('button');
  back.type = 'button';
  back.className = 'button sv-back-projects';
  back.textContent = 'Natrag na projekte';
  back.addEventListener('click', () => {
    // Activate projects tab/panel
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(t => t.classList.remove('sv-tab-active'));
    const projTab = document.querySelector('[data-tab="projects"]');
    if (projTab) projTab.classList.add('sv-tab-active');
    const panels = document.querySelectorAll('[data-panel]');
    panels.forEach(p => p.setAttribute('hidden',''));
    const projPanel = document.querySelector('[data-panel="projects"]');
    if (projPanel) {
      projPanel.removeAttribute('hidden');
      try { projPanel.scrollIntoView({ behavior: 'smooth' }); } catch(_){}
    }
  });

  const title = document.createElement('h3');
  title.textContent = getTitle(project) || 'Projekt';

  const secMeet = document.createElement('section');
  const hMeet = document.createElement('h4');
  hMeet.textContent = 'Susreti';
  const meetBox = document.createElement('div');
  secMeet.appendChild(hMeet);
  secMeet.appendChild(meetBox);

  const secPay = document.createElement('section');
  const hPay = document.createElement('h4');
  hPay.textContent = 'Plaćanja';
  const payBox = document.createElement('div');
  secPay.appendChild(hPay);
  secPay.appendChild(payBox);

  meetingsPanel.appendChild(back);
  meetingsPanel.appendChild(title);
  meetingsPanel.appendChild(secMeet);
  meetingsPanel.appendChild(secPay);

  // Populate sections
  try { if (typeof renderMeetings === 'function') renderMeetings(projectId, meetBox, NONCE); } catch(_){}
  try { if (typeof renderPayments === 'function') renderPayments(projectId, payBox); } catch(_){}

  // Switch to meetings tab/panel
  const tabs = document.querySelectorAll('[data-tab]');
  tabs.forEach(t => t.classList.remove('sv-tab-active'));
  const meetingsTab = document.querySelector('[data-tab="meetings"]');
  if (meetingsTab) meetingsTab.classList.add('sv-tab-active');
  const panels = document.querySelectorAll('[data-panel]');
  panels.forEach(p => p.setAttribute('hidden',''));
  meetingsPanel.removeAttribute('hidden');
  try { meetingsPanel.scrollIntoView({ behavior:'smooth' }); } catch(_){}
}

// Fetch and render meetings and payments for a project id
export async function loadProjectData(projectId, project) {
  const meetingsPanel = document.querySelector('[data-panel="meetings"]');
  if (!meetingsPanel) return;
  meetingsPanel.innerHTML = '';

  // Header with back button and project title
  const back = document.createElement('button');
  back.type = 'button';
  back.className = 'button sv-back-projects';
  back.textContent = 'Natrag na projekte';
  back.addEventListener('click', () => {
    SELECTED_PROJECT_ID = null;
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(t => t.classList.remove('sv-tab-active'));
    const projTab = document.querySelector('[data-tab="projects"]');
    if (projTab) projTab.classList.add('sv-tab-active');
    const panels = document.querySelectorAll('[data-panel]');
    panels.forEach(p => p.setAttribute('hidden',''));
    const projPanel = document.querySelector('[data-panel="projects"]');
    if (projPanel) {
      projPanel.removeAttribute('hidden');
      // Re-render list to ensure fresh state
      try { if (Array.isArray(window.__SV_PROJECTS)) renderProjectsList(projPanel, window.__SV_PROJECTS); } catch(_){}
      try { projPanel.scrollIntoView({ behavior:'smooth' }); } catch(_){}
    }
  });

  const title = document.createElement('h3');
  title.dataset.el = 'project-title';
  title.textContent = getTitle(project) || 'Projekt';

  // Sections shell
  const secMeet = document.createElement('section');
  const hMeet = document.createElement('h4');
  hMeet.textContent = 'Susreti';
  const meetBox = document.createElement('div');
  meetBox.textContent = 'Učitavanje...';
  secMeet.appendChild(hMeet);
  secMeet.appendChild(meetBox);

  const secPay = document.createElement('section');
  const hPay = document.createElement('h4');
  hPay.textContent = 'Plaćanja';
  const payBox = document.createElement('div');
  payBox.textContent = 'Učitavanje...';
  secPay.appendChild(hPay);
  secPay.appendChild(payBox);

  meetingsPanel.appendChild(back);
  meetingsPanel.appendChild(title);
  meetingsPanel.appendChild(secMeet);
  meetingsPanel.appendChild(secPay);

  // Switch to meetings tab/panel
  const tabs = document.querySelectorAll('[data-tab]');
  tabs.forEach(t => t.classList.remove('sv-tab-active'));
  const meetingsTab = document.querySelector('[data-tab="meetings"]');
  if (meetingsTab) meetingsTab.classList.add('sv-tab-active');
  const panels = document.querySelectorAll('[data-panel]');
  panels.forEach(p => p.setAttribute('hidden',''));
  meetingsPanel.removeAttribute('hidden');
  try { meetingsPanel.scrollIntoView({ behavior:'smooth' }); } catch(_){}

  // Fetch data
  const qs = new URLSearchParams({ project_id: String(projectId) }).toString();
  try {
    const [meetings, payments] = await Promise.all([
      apiGet(`meetings?${qs}`),
      apiGet(`payments?${qs}`)
    ]);

    // Render meetings: upcoming at top
    if (!Array.isArray(meetings) || meetings.length === 0) {
      renderEmpty(meetBox, 'Nema susreta.');
    } else {
      meetBox.innerHTML = '';
      const now = Date.now();
      const sorted = [...meetings].sort((a,b) => {
        const ta = Date.parse(a?.meeting_at || '') || 0;
        const tb = Date.parse(b?.meeting_at || '') || 0;
        const fa = ta > now; const fb = tb > now;
        if (fa && !fb) return -1; if (!fa && fb) return 1;
        if (fa && fb) return ta - tb; // both future asc
        return tb - ta; // both past desc
      });
      const ul = document.createElement('ul');
      sorted.forEach(m => {
        const li = document.createElement('li');
        const when = formatDateTime(m?.meeting_at);
        const status = m?.status || '';
        li.textContent = status ? `${when} — ${status}` : when;
        ul.appendChild(li);
      });
      meetBox.appendChild(ul);
    }

    // Render payments: table + summary
    const items = Array.isArray(payments?.items) ? payments.items : [];
    const summary = payments?.summary || {};
    payBox.innerHTML = '';
    if (items.length === 0) {
      renderEmpty(payBox, 'Nema plaćanja.');
    } else {
      const table = document.createElement('table');
      const thead = document.createElement('thead');
      const trh = document.createElement('tr');
      ['Naslov','Iznos','Rok','Status'].forEach(h => { const th = document.createElement('th'); th.textContent = h; trh.appendChild(th); });
      thead.appendChild(trh);
      const tbody = document.createElement('tbody');
      items.forEach(it => {
        const tr = document.createElement('tr');
        const amount = Number(it?.amount || 0);
        const disc = Number(it?.discount_pct || 0);
        const eff = amount - (amount * disc / 100);
        const cols = [
          it?.title || '',
          `${eff.toFixed(2)}${it?.currency ? ' ' + it.currency : ''}`,
          it?.due_at || '',
          it?.status || ''
        ];
        cols.forEach(val => { const td = document.createElement('td'); td.textContent = val; tr.appendChild(td); });
        tbody.appendChild(tr);
      });
      table.appendChild(thead); table.appendChild(tbody);
      payBox.appendChild(table);

      const sums = document.createElement('div');
      sums.className = 'text-xs';
      const fmt2 = (n) => { const v = Number(n || 0); return Number.isFinite(v) ? v.toFixed(2) : '0.00'; };
      sums.textContent = `Ukupno: ${fmt2(summary.total)} — Plaćeno: ${fmt2(summary.paid)} — Otvoreno: ${fmt2(summary.open)}`;
      payBox.appendChild(sums);
    }

  } catch (e) {
    renderError(meetBox, 'Greška pri dohvaćanju.');
    renderError(payBox, 'Greška pri dohvaćanju.');
  }
}

function apiGet(path, nonceOverride) {
  const url = apiUrl(path);
  return fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': nonceOverride ?? NONCE,
      'Accept': 'application/json'
    },
    credentials: 'same-origin'
  }).then(async (res) => {
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      const err = new Error(`HTTP ${res.status} ${res.statusText}`);
      err.responseText = text;
      throw err;
    }
    return res.json();
  });
}

function apiPost(path, body, nonceOverride) {
  const url = apiUrl(path);
  return fetch(url, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': nonceOverride ?? NONCE,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    credentials: 'same-origin',
    body: JSON.stringify(body || {})
  }).then(async (res) => {
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      const err = new Error(`HTTP ${res.status} ${res.statusText}`);
      err.responseText = text;
      throw err;
    }
    // some REST endpoints may return empty body; try json, fallback to empty object
    try { return await res.json(); } catch (_) { return {}; }
  });
}

function setupTabs(root = document) {
  const tabs = Array.from(root.querySelectorAll('[data-tab]'));
  const panels = Array.from(root.querySelectorAll('[data-panel]'));
  if (!tabs.length || !panels.length) return;

  const show = (key) => {
    tabs.forEach(t => t.setAttribute('aria-selected', String(t.dataset.tab === key)));
    panels.forEach(p => {
      const match = p.dataset.panel === key;
      if (match) {
        p.removeAttribute('hidden');
      } else {
        p.setAttribute('hidden', '');
      }
    });
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      show(tab.dataset.tab);
    });
  });

  const initiallySelected = tabs.find(t => t.getAttribute('aria-selected') === 'true');
  show((initiallySelected || tabs[0]).dataset.tab);
}

// Best-effort accessors to accommodate varying API shapes
function getTitle(p) {
  return (p && (p.title?.rendered || p.title || p.post_title || p.name)) || '—';
}

function getProgram(p) {
  return (p && (p.program?.name || p.program || p.meta?.program)) || '';
}

function getDateRange(p) {
  if (!p) return '';
  const start = p.start_date || p.start || p.date_start || p.startDate || p?.dates?.start || '';
  const end = p.end_date || p.end || p.date_end || p.endDate || p?.dates?.end || '';
  const single = p.date || p.when || '';
  if (start && end) return `${start} — ${end}`;
  if (start) return String(start);
  if (single) return String(single);
  return '';
}

function renderMiniProjects(container, projects) {
  if (!container) return;
  if (!Array.isArray(projects) || projects.length === 0) {
    renderEmpty(container, 'Nema projekata.');
    return;
  }
  container.innerHTML = '';
  const ul = document.createElement('ul');
  projects.slice(0, 3).forEach(p => {
    const li = document.createElement('li');
    const title = getTitle(p);
    const program = getProgram(p);
    li.textContent = program ? `${title} — ${program}` : String(title);
    ul.appendChild(li);
  });
  container.appendChild(ul);
}

function renderAllProjects(container, projects) {
  if (!container) return;
  if (!Array.isArray(projects) || projects.length === 0) {
    renderEmpty(container, 'Nema projekata.');
    return;
  }
  container.innerHTML = '';
  const ul = document.createElement('ul');
  projects.forEach(p => {
    const li = document.createElement('li');
    const title = getTitle(p);
    const program = getProgram(p);
    const dates = getDateRange(p);
    const parts = [title];
    if (program) parts.push(program);
    if (dates) parts.push(dates);
    li.textContent = parts.join(' — ');
    ul.appendChild(li);
  });
  container.appendChild(ul);
}

async function initPortal() {
  setupTabs(document);

  // Fetch user context (me) and projects in parallel; ignore "me" result for now.
  const miniEl = document.querySelector('[data-mini-projects]');
  const projectsPanel = document.querySelector('[data-panel="projects"]');

  const mePromise = apiGet('me').catch(() => null);
  const projectsPromise = apiGet('projects');

  try {
    await mePromise; // fetched to satisfy requirement; not used further here
  } catch (_) {
    // Ignore; auth may still allow projects or will fail there too
  }

  try {
    const projects = await projectsPromise;
    // dashboard cache
    window.__SV_PROJECTS = projects;
    renderMiniProjects(miniEl, projects);
    // Render selectable project cards into the projects panel
    renderProjectsList(projectsPanel, projects);
    // Auto-select first project and render dependent panels
    if (Array.isArray(projects) && projects.length > 0) {
      const firstId = Number(projects[0]?.id ?? projects[0]?.ID ?? 0) || null;
      if (firstId) {
        SELECTED_PROJECT_ID = firstId;
        // mark the first card active
        const firstCard = projectsPanel?.querySelector('.project-card');
        if (firstCard) firstCard.classList.add('active');
        // Render combined detail view
        loadProjectData(SELECTED_PROJECT_ID, projects[0]);
        try { if (typeof renderDashboard === 'function') { renderDashboard(); } } catch (e) {}
      }
    }
  } catch (err) {
    renderError(miniEl, 'Greška pri učitavanju.');
    renderError(projectsPanel, 'Greška pri učitavanju.');
    // Optional: console debug without leaking to UI
    // console.error('Projects fetch failed', err);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPortal);
} else {
  initPortal();
}

// Render dashboard KPIs and upcoming meeting
export async function renderDashboard() {
  try {
    const projects = Array.isArray(window.__SV_PROJECTS) ? window.__SV_PROJECTS : await apiGet('projects');
    if (!Array.isArray(window.__SV_PROJECTS)) window.__SV_PROJECTS = projects;
    if (!projects || projects.length === 0) return;
    const project = projects[0];
    const projectId = project?.id ?? project?.ID;
    if (!projectId) return;

    // Upcoming meeting section
    const upcomingProjectEl = document.querySelector('[data-upcoming-project]');
    const upcomingTimeEl = document.querySelector('[data-upcoming-time]');
    const upcomingDeadlineEl = document.querySelector('[data-upcoming-deadline]');
    try {
      const meetings = await apiGet(`meetings?project_id=${encodeURIComponent(projectId)}`);
      const now = Date.now();
      let nearest = null;
      for (const m of (meetings || [])){
        const t = Date.parse(m?.meeting_at || '');
        if (!isNaN(t) && t > now){
          if (!nearest || t < Date.parse(nearest.meeting_at || '')) nearest = m;
        }
      }
      if (nearest) {
        if (upcomingProjectEl) upcomingProjectEl.textContent = getTitle(project);
        if (upcomingTimeEl) upcomingTimeEl.textContent = formatDateTime(nearest.meeting_at);
        if (upcomingDeadlineEl) upcomingDeadlineEl.textContent = formatDateTime(nearest.upload_deadline_at);
      }
    } catch (e) {
      if (upcomingProjectEl) upcomingProjectEl.textContent = 'Greška pri učitavanju.';
      if (upcomingTimeEl) upcomingTimeEl.textContent = 'Greška pri učitavanju.';
      if (upcomingDeadlineEl) upcomingDeadlineEl.textContent = 'Greška pri učitavanju.';
    }

    // Payments summary section
    try {
      const data = await apiGet(`payments?project_id=${encodeURIComponent(projectId)}`);
      const summary = data?.summary || {};
      const fmt = (n) => {
        const num = Number(n || 0);
        return Number.isFinite(num) ? num.toFixed(2) : '0.00';
      };
      const sumTotal = document.querySelector('[data-sum-total]');
      const sumPaid = document.querySelector('[data-sum-paid]');
      const sumOpen = document.querySelector('[data-sum-open]');
      if (sumTotal) sumTotal.textContent = fmt(summary.total);
      if (sumPaid) sumPaid.textContent = fmt(summary.paid);
      if (sumOpen) sumOpen.textContent = fmt(summary.open);
    } catch (e) {
      const errs = document.querySelectorAll('[data-sum-total], [data-sum-paid], [data-sum-open]');
      errs.forEach(el => { el.textContent = 'Greška pri učitavanju.'; });
    }
  } catch (_) {
    // noop
  }
}

// Render meetings list for a given project into a container element.
// Signature required: renderMeetings(projectId, container, nonce)
export async function renderMeetings(projectId, container, nonce) {
  try {
    if (!container) return;
    container.innerHTML = '';

    const qs = new URLSearchParams({ project_id: String(projectId) });
    const meetings = await apiGet(`meetings?${qs.toString()}`, nonce);

    if (!Array.isArray(meetings) || meetings.length === 0) {
      const empty = document.createElement('div');
      empty.textContent = 'Nema susreta.';
      container.appendChild(empty);
      return;
    }

    meetings.forEach((m) => {
      const id = m?.id ?? m?.ID ?? m?.meeting_id;
      const date = m?.meeting_at || '';
      const status = m?.status || '';
      const canUpload = !!m?.can_upload;

      const card = document.createElement('div');
      card.className = 'meeting-card';

      const header = document.createElement('div');
      const dateEl = document.createElement('span');
      dateEl.textContent = date ? formatDateTime(date) : '';
      const statusEl = document.createElement('span');
      statusEl.className = 'status-badge';
      statusEl.textContent = status ? String(status) : '';
      header.appendChild(dateEl);
      if (status) {
        const sep = document.createTextNode(' ');
        header.appendChild(sep);
        header.appendChild(statusEl);
      }
      card.appendChild(header);

      const actions = document.createElement('div');
      // Submitted file info
      if (m?.client_upload) {
        const info = document.createElement('div');
        info.className = 'text-xs';
        const when = m.client_upload.submitted_at ? formatDateTime(m.client_upload.submitted_at) : '';
        info.textContent = `Predano: ${m.client_upload.filename || ''}${when ? ' ('+when+')' : ''}`;
        actions.appendChild(info);
      }

      const uploadBtn = document.createElement('button');
      uploadBtn.type = 'button';
      uploadBtn.textContent = 'Predaj';

      // Upload UI wrapper
      const uploadWrap = document.createElement('div');
      uploadWrap.className = 'sv-upload-wrap';
      const fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.accept = '.pdf,.doc,.docx,.txt,.rtf';
      fileInput.hidden = true;
      const progress = document.createElement('progress');
      progress.className = 'sv-upload-progress';
      progress.max = 100;
      progress.value = 0;
      progress.hidden = true;
      const msg = document.createElement('div');
      msg.className = 'sv-upload-msg';
      msg.setAttribute('aria-live', 'polite');

      if (!canUpload) {
        uploadBtn.disabled = true;
        const note = document.createElement('div');
        note.className = 'text-xs';
        note.textContent = 'Predaja je omogućena do 72 h prije susreta.';
        uploadWrap.appendChild(note);
        if (m?.upload_deadline_at) {
          const dl = document.createElement('div');
          dl.className = 'text-xs';
          dl.textContent = `Rok: ${formatDateTime(m.upload_deadline_at)}`;
          uploadWrap.appendChild(dl);
        }
        const past = m?.meeting_at ? (Date.parse(m.meeting_at) < Date.now()) : false;
        const held = m?.status === 'odrzan' || m?.status === 'odrzano';
        if (held || past) {
          const heldMsg = document.createElement('div');
          heldMsg.className = 'text-xs';
          heldMsg.textContent = 'Susret je održan — predaja nije dostupna.';
          uploadWrap.appendChild(heldMsg);
        }
      } else {
        // Wire up upload interactions
        uploadBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
          const file = fileInput.files && fileInput.files[0];
          if (!file) return;
          // quick validations
          const allowed = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/rtf'
          ];
          if (file.size > 20 * 1024 * 1024) { msg.textContent = 'Datoteka je veća od 20 MB.'; return; }
          if (file.type && !allowed.includes(file.type)) { msg.textContent = 'Nepodržan tip datoteke.'; return; }

          // prepare upload
          uploadBtn.disabled = true;
          progress.hidden = false;
          progress.value = 0;
          msg.textContent = 'Prijenos u tijeku...';

          const fd = new FormData();
          fd.append('file', file);

          const xhr = new XMLHttpRequest();
          xhr.open('POST', apiUrl(`meetings/${encodeURIComponent(id)}/upload`), true);
          xhr.setRequestHeader('X-WP-Nonce', nonce ?? NONCE);

          xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
              const pct = Math.round((e.loaded / e.total) * 100);
              progress.value = pct;
            }
          };
          xhr.onreadystatechange = () => {
            if (xhr.readyState !== 4) return;
            uploadBtn.disabled = false;
            progress.hidden = true;
            if (xhr.status >= 200 && xhr.status < 300) {
              msg.textContent = 'Uspješno predano.';
              // refresh list
              renderMeetings(projectId, container, nonce);
            } else {
              let human = 'Greška pri predaji.';
              if (xhr.status === 401 || xhr.status === 403) human = 'Nemate dopuštenje za predaju.';
              if (xhr.status === 413) human = 'Datoteka je prevelika (ograničenje servera).';
              if (xhr.status === 415) human = 'Nepodržan tip datoteke.';
              if (xhr.status === 422) human = 'Neispravan zahtjev (validacija nije prošla).';
              if (xhr.status >= 500) human = 'Greška na poslužitelju.';
              msg.textContent = human;
            }
          };
          xhr.onerror = () => {
            uploadBtn.disabled = false;
            progress.hidden = true;
            msg.textContent = 'Greška mreže pri predaji.';
          };
          xhr.send(fd);
        });
      }

      actions.appendChild(uploadBtn);
      actions.appendChild(fileInput);
      uploadWrap.appendChild(progress);
      uploadWrap.appendChild(msg);
      actions.appendChild(uploadWrap);
      card.appendChild(actions);

      // Supplemental info links
      const infoWrap = document.createElement('div');
      infoWrap.className = 'text-xs';
      const makeLink = (href, text) => {
        const d = document.createElement('div');
        d.className = 'text-xs';
        const a = document.createElement('a');
        a.href = href;
        a.textContent = text;
        a.rel = 'noopener';
        d.appendChild(a);
        return d;
      };
      // already shown above as text; keep download links below as well if desired
      if (m?.client_upload?.id) {
        const href = apiUrl(`files/client/${encodeURIComponent(m.client_upload.id)}`);
        card.appendChild(makeLink(href, 'Preuzmi predano'));
      }
      if (m?.coach_attachment?.id) {
        const href = apiUrl(`files/coach/${encodeURIComponent(m.coach_attachment.id)}`);
        card.appendChild(makeLink(href, 'Materijal savjetnika: Preuzmi'));
      }

      const notesWrap = document.createElement('div');
      const textarea = document.createElement('textarea');
      textarea.placeholder = 'Vaše bilješke...';
      textarea.value = m?.client_notes || '';
      const saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.textContent = 'Spremi';
      saveBtn.addEventListener('click', async () => {
        try {
          saveBtn.disabled = true;
          const notes = textarea.value || '';
          await apiPost(`meetings/${encodeURIComponent(id)}/client-notes`, { notes }, nonce);
        } catch (err) {
          console.error('Spremanje bilješki nije uspjelo:', err);
        } finally {
          saveBtn.disabled = false;
        }
      });
      notesWrap.appendChild(textarea);
      notesWrap.appendChild(saveBtn);
      card.appendChild(notesWrap);

      container.appendChild(card);
    });
  } catch (err) {
    console.error('Učitavanje susreta nije uspjelo:', err);
    if (container) {
      container.textContent = 'Greška pri učitavanju.';
    }
  }
}

// Render payments list and update summary widgets
export async function renderPayments(projectId, container) {
  try {
    if (!container) return;
    container.innerHTML = '';

    const data = await apiGet(`payments?project_id=${encodeURIComponent(projectId)}`, NONCE);
    const summary = data?.summary || {};
    const items = Array.isArray(data?.items) ? data.items : [];

    // Update summary widgets if present
    const fmt = (n) => {
      const num = Number(n || 0);
      return Number.isFinite(num) ? num.toFixed(2) : '0.00';
    };
    const sumTotal = document.querySelector('[data-sum-total]');
    const sumPaid = document.querySelector('[data-sum-paid]');
    const sumOpen = document.querySelector('[data-sum-open]');
    if (sumTotal) sumTotal.textContent = fmt(summary.total);
    if (sumPaid) sumPaid.textContent = fmt(summary.paid);
    if (sumOpen) sumOpen.textContent = fmt(summary.open);

    if (!items.length) {
      const empty = document.createElement('div');
      empty.textContent = 'Nema plaćanja.';
      container.appendChild(empty);
      return;
    }

    items.forEach((it) => {
      const row = document.createElement('div');
      row.className = 'payment-row';
      row.dataset.paymentId = String(it?.id || '');
      const title = it?.title || '';
      const amount = Number(it?.amount || 0);
      const disc = Number(it?.discount_pct || 0);
      const eff = amount - (amount * disc / 100);
      const due = it?.due_at || '';
      const status = it?.status || '';

      const t = document.createElement('span');
      t.className = 'payment-title';
      t.textContent = title;
      const a = document.createElement('span');
      a.className = 'payment-amount';
      a.textContent = `${eff.toFixed(2)}${it?.currency ? ' ' + it.currency : ''}`;
      const d = document.createElement('span');
      d.className = 'payment-due';
      d.textContent = due;
      const s = document.createElement('span');
      s.className = 'payment-status';
      s.textContent = status;

      row.appendChild(t);
      row.appendChild(a);
      row.appendChild(d);
      row.appendChild(s);
      container.appendChild(row);
    });
  } catch (err) {
    console.error('Učitavanje plaćanja nije uspjelo:', err);
    if (container) container.textContent = 'Greška pri učitavanju.';
  }
}
