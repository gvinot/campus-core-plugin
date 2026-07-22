// Bloc "Mes blogs publiés" — liste + suppression
// S'auto-désactive si le conteneur #my-blogs est absent.
(function () {

  function waitForData(cb) {
    if (window.CampusData && CampusData.nonce) return cb();
    setTimeout(function () { waitForData(cb); }, 100);
  }

  function apiFetch(endpoint, options) {
    options = options || {};
    return fetch(CampusData.apiUrl + endpoint, Object.assign({
      credentials: 'include',
      headers: Object.assign(
        { 'Content-Type': 'application/json', 'X-WP-Nonce': CampusData.nonce },
        options.headers || {}
      )
    }, options)).then(function (r) {
      return r.json().then(function (d) { return { ok: r.ok, data: d }; });
    });
  }

  function escHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function init() {
    var container = document.getElementById('my-blogs');
    if (!container) return;

    var myId = null;

    function load() {
      apiFetch('/users/' + myId + '/blogs?per_page=50').then(function (res) {
        var blogs = (res.data && res.data.blogs) || [];

        if (!blogs.length) {
          container.innerHTML = '<p style="color:#999;font-size:14px;">Tu n\'as pas encore publie de blog.</p>';
          return;
        }

        container.innerHTML = '';
        blogs.forEach(function (b) {
          var date = new Date(b.date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
          var row = document.createElement('div');
          row.className = 'mb-row';
          row.style.cssText = 'display:flex;gap:16px;padding:14px 0;border-bottom:1px solid #f0f0f0;align-items:center;';
          row.innerHTML =
            '<div style="width:74px;height:56px;border-radius:8px;flex-shrink:0;background-color:#1B3A5C;background-size:cover;background-position:center;'
              + (b.thumbnail ? 'background-image:url(' + b.thumbnail + ');' : '') + '"></div>'
            + '<div style="flex:1;min-width:0;">'
            + '  <a href="' + b.permalink + '" style="color:#1B3A5C;font-weight:600;text-decoration:none;font-size:15px;">' + escHtml(b.title) + '</a>'
            + '  <div style="font-size:12px;color:#888;margin-top:4px;">' + date + ' - ' + (b.like_count || 0) + ' j\'aime</div>'
            + '</div>'
            + '<button class="mb-delete" data-id="' + b.id + '" style="background:#94a3b8;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Supprimer</button>';
          container.appendChild(row);
        });

        container.querySelectorAll('.mb-delete').forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (!confirm('Supprimer ce blog definitivement ?')) return;
            btn.disabled = true; btn.textContent = '...';
            apiFetch('/blogs/' + btn.dataset.id, { method: 'DELETE' }).then(function (res) {
              if (res.ok && res.data.success) {
                load();
              } else {
                btn.disabled = false; btn.textContent = 'Supprimer';
                alert((res.data && res.data.error) || 'Erreur.');
              }
            });
          });
        });
      });
    }

    waitForData(function () {
      apiFetch('/users/me').then(function (res) {
        if (!res.data || !res.data.user) return;
        myId = res.data.user.id;
        load();
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
