// Admin Meetings (REST-driven)
// Requires localized SV = { nonce, rest, projectId }

(function(){
  if (typeof window === 'undefined') return;
  const API = (SV && SV.rest) ? SV.rest.replace(/\/+$/, '') + '/' : '/wp-json/sv/v1/';
  const NONCE = SV && SV.nonce;
  const PROJECT_ID = SV && SV.projectId;

  const apiUrl = (p) => API + String(p).replace(/^\/+/, '');
  const headers = () => ({ 'X-WP-Nonce': NONCE, 'Accept':'application/json', 'Content-Type': 'application/json' });
  const get = (path) => fetch(apiUrl(path), { headers: {'X-WP-Nonce': NONCE, 'Accept':'application/json'}, credentials:'same-origin' }).then(r=>r.json());
  const post = (path, body) => fetch(apiUrl(path), { method:'POST', headers: headers(), credentials:'same-origin', body: JSON.stringify(body||{}) }).then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
  const put  = (path, body) => fetch(apiUrl(path), { method:'PUT',  headers: headers(), credentials:'same-origin', body: JSON.stringify(body||{}) }).then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
  const del  = (path)       => fetch(apiUrl(path), { method:'DELETE', headers: {'X-WP-Nonce': NONCE, 'Accept':'application/json'}, credentials:'same-origin' }).then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });

  const fmt = (iso) => { try { const d = new Date(iso); return d.toLocaleString('hr-HR', { dateStyle:'medium', timeStyle:'short' }); } catch(_) { return iso || ''; } };

  async function renderList(){
    const box = document.getElementById('sv_meetings_list');
    if (!box) return;
    box.innerHTML = '<em>Učitavanje…</em>';
    try {
      const qs = new URLSearchParams({ project_id:String(PROJECT_ID) }).toString();
      const items = await get('meetings?' + qs);
      if (!Array.isArray(items) || items.length === 0){ box.innerHTML = '<em>Nema susreta.</em>'; return; }
      const table = document.createElement('table'); table.className = 'widefat striped';
      const thead = document.createElement('thead'); const trh = document.createElement('tr');
      ['Datum/Vrijeme','Status','Bilješke','Akcije'].forEach(h=>{ const th=document.createElement('th'); th.textContent=h; trh.appendChild(th); });
      thead.appendChild(trh); table.appendChild(thead);
      const tbody = document.createElement('tbody');
      items.sort((a,b)=> new Date(b.meeting_at)-new Date(a.meeting_at)).forEach(m=>{
        const tr = document.createElement('tr'); tr.dataset.id = String(m.id);
        const tdWhen = document.createElement('td'); tdWhen.textContent = fmt(m.meeting_at);
        const tdStatus = document.createElement('td'); tdStatus.textContent = m.status || '';
        const tdNotes = document.createElement('td'); tdNotes.textContent = m.coach_notes || '';
        const tdAct = document.createElement('td');
        const btnE = document.createElement('button'); btnE.type='button'; btnE.className='button'; btnE.textContent='Uredi';
        const btnD = document.createElement('button'); btnD.type='button'; btnD.className='button link-delete'; btnD.textContent='Obriši'; btnD.style.marginLeft='8px';
        tdAct.appendChild(btnE); tdAct.appendChild(btnD);
        tr.appendChild(tdWhen); tr.appendChild(tdStatus); tr.appendChild(tdNotes); tr.appendChild(tdAct); tbody.appendChild(tr);
      });
      table.appendChild(tbody); box.innerHTML = ''; box.appendChild(table);
    } catch (e) { box.textContent = 'Greška pri dohvaćanju.'; }
  }

  function readForm(){
    const at = document.getElementById('sv_meeting_at');
    const st = document.getElementById('sv_meeting_status');
    const nt = document.getElementById('sv_meeting_notes');
    const val = (at && at.value) ? at.value : '';
    let iso = '';
    try { iso = new Date(val).toISOString(); } catch(_) { iso = ''; }
    return { meeting_at: iso, status: st ? st.value : 'zakazano', coach_notes: nt ? nt.value : '' };
  }

  function toInlineEdit(tr, m){
    tr.innerHTML = '';
    const tdWhen = document.createElement('td');
    const at = document.createElement('input'); at.type='datetime-local';
    try { const d = new Date(m.meeting_at); at.value = d.toISOString().slice(0,16); } catch(_) {}
    tdWhen.appendChild(at);
    const tdStatus = document.createElement('td');
    const st = document.createElement('select'); ['zakazano','odrzano','otkazano'].forEach(s=>{ const o=document.createElement('option'); o.value=s; o.textContent=s; if(m.status===s||m.status=== (s==='zakazano'?'zakazan':(s==='odrzano'?'odrzan':s))) o.selected=true; st.appendChild(o); });
    tdStatus.appendChild(st);
    const tdNotes = document.createElement('td'); const nt = document.createElement('textarea'); nt.rows=2; nt.style.width='100%'; nt.value = m.coach_notes || ''; tdNotes.appendChild(nt);
    const tdAct = document.createElement('td'); const sv = document.createElement('button'); sv.type='button'; sv.className='button button-primary'; sv.textContent='Spremi'; tdAct.appendChild(sv);
    tr.appendChild(tdWhen); tr.appendChild(tdStatus); tr.appendChild(tdNotes); tr.appendChild(tdAct);
    sv.addEventListener('click', async ()=>{
      try {
        const iso = at.value ? new Date(at.value).toISOString() : m.meeting_at;
        const body = { meeting_at: iso, status: st.value, coach_notes: nt.value };
        await put('meetings/' + encodeURIComponent(m.id), body);
        await renderList();
      } catch (e) { alert('Greška pri spremanju.'); }
    });
  }

  document.addEventListener('click', async function(e){
    const t = e.target;
    if (t && t.id === 'sv_add_meeting'){
      e.preventDefault();
      const body = readForm();
      if (!body.meeting_at){ alert('Unesite datum i vrijeme.'); return; }
      try {
        await post('meetings', { project_id: PROJECT_ID, meeting_at: body.meeting_at, status: body.status, coach_notes: body.coach_notes });
        const at = document.getElementById('sv_meeting_at'); if (at) at.value='';
        const nt = document.getElementById('sv_meeting_notes'); if (nt) nt.value='';
        await renderList();
      } catch (err){ alert('Greška pri dodavanju.'); }
    }

    if (t && t.classList.contains('button')){
      const tr = t.closest('tr'); if (!tr) return;
      const id = tr && tr.dataset.id ? parseInt(tr.dataset.id,10) : 0;
      if (t.textContent === 'Uredi'){
        // Load current data from row into inline editor (fetch fresh to be safe)
        try {
          const qs = new URLSearchParams({ project_id: String(PROJECT_ID) }).toString();
          const items = await get('meetings?' + qs);
          const m = (items || []).find(x => String(x.id) === String(id));
          if (m) toInlineEdit(tr, m);
        } catch(_){}
      }
      if (t.classList.contains('link-delete')){
        if (!confirm('Obriši susret?')) return;
        try { await del('meetings/' + encodeURIComponent(id)); await renderList(); } catch(_) { alert('Greška pri brisanju.'); }
      }
    }
  });

  document.addEventListener('DOMContentLoaded', renderList);
})();

