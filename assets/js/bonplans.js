// Bons plans — LOT 14
// Chargé comme fichier plugin (évite les problèmes de <script> inline dans Elementor).
(function () {

  function init() {
    var app = document.getElementById('bonplans-app');
    if (!app) return;

    var CATS = {
      logement: 'Logement', nourriture: 'Nourriture', transport: 'Transport',
      sortie: 'Sortie', demarches: 'Démarches', autre: 'Autre'
    };

    function waitForData(cb) {
      if (window.CampusData && CampusData.nonce) return cb();
      setTimeout(function () { waitForData(cb); }, 100);
    }

    function apiFetch(endpoint, options) {
      options = options || {};
      return fetch(CampusData.apiUrl + endpoint, Object.assign({
        credentials: 'include',
        headers: Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': CampusData.nonce }, options.headers || {})
      }, options)).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    function render(list) {
      var el = document.getElementById('bp-list');
      if (!list.length) {
        el.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;">Aucun bon plan pour ces criteres.</p>';
        return;
      }
      el.innerHTML = '';
      list.forEach(function (bp) {
        var card = document.createElement('div');
        card.style.cssText = 'background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:20px;display:flex;flex-direction:column;';

        var badges = '<span style="background:#1B3A5C;color:#fff;font-size:11px;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;">' + escHtml(bp.category_label) + '</span>';
        if (bp.destination && bp.destination.name) {
          badges += '<span style="background:#f0f0f0;color:#555;font-size:11px;padding:4px 10px;border-radius:20px;">' + escHtml(bp.destination.name) + '</span>';
        }
        if (bp.is_safe) {
          badges += '<span style="background:#3B6D11;color:#fff;font-size:11px;padding:4px 10px;border-radius:20px;">Valeur sure</span>';
        }

        var priceHtml = bp.price ? '<p style="color:#E8621A;font-weight:600;font-size:14px;margin:0 0 8px;">' + escHtml(bp.price) + '</p>' : '';
        var linkHtml = bp.link ? '<a href="' + bp.link + '" target="_blank" rel="noopener" style="color:#E8621A;font-size:13px;text-decoration:none;margin-bottom:12px;display:inline-block;">Voir le lien</a>' : '';

        card.innerHTML =
          '<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">' + badges + '</div>' +
          '<h3 style="color:#1B3A5C;margin:0 0 8px;font-size:17px;">' + escHtml(bp.title) + '</h3>' +
          '<p style="color:#666;font-size:14px;margin:0 0 12px;flex:1;">' + escHtml(bp.description) + '</p>' +
          priceHtml + linkHtml +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;border-top:1px solid #f0f0f0;padding-top:12px;">' +
            '<a href="/membre?id=' + bp.author.id + '" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#888;font-size:13px;">' +
              '<img src="' + bp.author.avatar_url + '" style="width:26px;height:26px;border-radius:50%;"> ' + escHtml(bp.author.name) +
            '</a>' +
            '<button class="bp-vote" data-id="' + bp.id + '" style="display:flex;align-items:center;gap:6px;background:' + (bp.voted_by_me ? '#E8621A' : '#fff') + ';color:' + (bp.voted_by_me ? '#fff' : '#E8621A') + ';border:1.5px solid #E8621A;border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600;cursor:pointer;">' +
              'Utile <span class="bp-vote-count">' + bp.votes + '</span>' +
            '</button>' +
          '</div>';
        el.appendChild(card);
      });

      el.querySelectorAll('.bp-vote').forEach(function (btn) {
        btn.addEventListener('click', function () {
          btn.disabled = true;
          apiFetch('/bonplans/' + btn.dataset.id + '/vote', { method: 'POST' }).then(function (res) {
            btn.disabled = false;
            if (!res.ok || !res.data.success) return;
            var voted = res.data.voted;
            btn.style.background = voted ? '#E8621A' : '#fff';
            btn.style.color = voted ? '#fff' : '#E8621A';
            btn.querySelector('.bp-vote-count').textContent = res.data.votes;
          });
        });
      });
    }

    function load() {
      var d = document.getElementById('bp-destination').value;
      var c = document.getElementById('bp-categorie').value;
      var s = document.getElementById('bp-sort').value;
      document.getElementById('bp-list').innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;">Chargement...</p>';
      apiFetch('/bonplans?destination=' + d + '&categorie=' + c + '&sort=' + s).then(function (res) {
        render(res.data.bonplans || []);
      });
    }

    var toggleBtn = document.getElementById('bp-toggle-form');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        var f = document.getElementById('bp-form');
        f.style.display = (f.style.display === 'none' || !f.style.display) ? 'block' : 'none';
      });
    }

    waitForData(function () {
      var catSel = document.getElementById('bp-categorie');
      var formCatSel = document.getElementById('bpf-cat');
      Object.keys(CATS).forEach(function (k) {
        catSel.insertAdjacentHTML('beforeend', '<option value="' + k + '">' + CATS[k] + '</option>');
        formCatSel.insertAdjacentHTML('beforeend', '<option value="' + k + '">' + CATS[k] + '</option>');
      });

      apiFetch('/destinations').then(function (res) {
        var dests = res.data.destinations || [];
        var destSel = document.getElementById('bp-destination');
        var formDestSel = document.getElementById('bpf-dest');
        dests.forEach(function (dest) {
          destSel.insertAdjacentHTML('beforeend', '<option value="' + dest.id + '">' + escHtml(dest.name) + '</option>');
          formDestSel.insertAdjacentHTML('beforeend', '<option value="' + dest.id + '">' + escHtml(dest.name) + '</option>');
        });
      });

      ['bp-destination', 'bp-categorie', 'bp-sort'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', load);
      });

      document.getElementById('bpf-submit').addEventListener('click', function () {
        var btn = this, fb = document.getElementById('bpf-feedback');
        var payload = {
          title: document.getElementById('bpf-title').value.trim(),
          description: document.getElementById('bpf-desc').value.trim(),
          category: document.getElementById('bpf-cat').value,
          destination_id: document.getElementById('bpf-dest').value,
          price: document.getElementById('bpf-price').value.trim(),
          link: document.getElementById('bpf-link').value.trim()
        };
        if (!payload.title || !payload.description) {
          fb.style.color = '#A32D2D'; fb.textContent = 'Titre et description obligatoires.'; return;
        }
        btn.disabled = true; btn.textContent = '...';
        apiFetch('/bonplans/create', { method: 'POST', body: JSON.stringify(payload) }).then(function (res) {
          btn.disabled = false; btn.textContent = 'Proposer';
          if (res.ok && res.data.success) {
            fb.style.color = '#3B6D11'; fb.textContent = 'Bon plan envoye ! Il sera visible apres validation.';
            document.getElementById('bpf-title').value = '';
            document.getElementById('bpf-desc').value = '';
            document.getElementById('bpf-price').value = '';
            document.getElementById('bpf-link').value = '';
          } else {
            fb.style.color = '#A32D2D'; fb.textContent = res.data.error || 'Erreur.';
          }
        });
      });

      load();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
