// Publication d'un blog (avec photo obligatoire et co-auteurs)
// La bio est désormais gérée sur la page "Mon compte" (account.js).
// Ce script s'auto-désactive si le formulaire de publication est absent.
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
    var card = document.getElementById('blog-publish-card');
    if (!card) return; // pas de formulaire sur cette page

    var imageBase64 = null;
    var selectedCoauthors = [];

    function renderChips() {
      var wrap = document.getElementById('coauthor-chips');
      if (!wrap) return;
      wrap.innerHTML = selectedCoauthors.map(function (c) {
        return '<span style="display:inline-flex;align-items:center;gap:6px;background:#1B3A5C;color:#fff;border-radius:20px;padding:4px 12px;font-size:13px;margin:0 6px 6px 0;">'
          + escHtml(c.name)
          + '<span data-id="' + c.id + '" class="chip-remove" style="cursor:pointer;font-weight:700;">x</span>'
          + '</span>';
      }).join('');
      wrap.querySelectorAll('.chip-remove').forEach(function (x) {
        x.addEventListener('click', function () {
          selectedCoauthors = selectedCoauthors.filter(function (c) { return String(c.id) !== x.dataset.id; });
          renderChips();
        });
      });
    }

    waitForData(function () {

      // Le formulaire n'est visible que pour les blogueurs et les admins
      apiFetch('/users/me').then(function (res) {
        var me = res.data && res.data.user;
        if (me && (me.role === 'blogger' || me.role === 'admin')) {
          card.style.display = 'block';
        }
      });

      /* ---------------- IMAGE (obligatoire) ---------------- */
      var imgInput = document.getElementById('blog-image');
      imgInput.addEventListener('change', function (e) {
        var file = e.target.files[0];
        var fb   = document.getElementById('blog-feedback');
        if (!file) { imageBase64 = null; document.getElementById('image-preview').style.display = 'none'; return; }
        if (file.size > 5 * 1024 * 1024) {
          fb.style.color = '#A32D2D';
          fb.textContent = 'Image trop lourde (max 5 Mo).';
          imgInput.value = ''; imageBase64 = null;
          return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
          imageBase64 = ev.target.result;
          document.getElementById('preview-img').src = imageBase64;
          document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
      });

      /* ---------------- CO-AUTEURS (amis uniquement) ---------------- */
      var coSearch  = document.getElementById('coauthor-search');
      var coResults = document.getElementById('coauthor-results');
      var coTimer;

      if (coSearch) {
        coSearch.addEventListener('input', function () {
          clearTimeout(coTimer);
          var q = coSearch.value.trim();
          if (q.length < 2) { coResults.innerHTML = ''; return; }
          coTimer = setTimeout(function () {
            apiFetch('/users/search?q=' + encodeURIComponent(q)).then(function (res) {
              var users = ((res.data && res.data.users) || []).filter(function (u) {
                return u.friendship === 'friends';
              });
              if (!users.length) {
                coResults.innerHTML = '<div style="color:#999;font-size:13px;padding:6px;">Aucun ami trouve.</div>';
                return;
              }
              coResults.innerHTML = users.map(function (u) {
                return '<div class="co-pick" data-id="' + u.id + '" data-name="' + escHtml(u.name) + '" '
                  + 'style="padding:8px;cursor:pointer;border-radius:6px;display:flex;align-items:center;gap:8px;">'
                  + '<img src="' + u.avatar_url + '" style="width:24px;height:24px;border-radius:50%;">'
                  + escHtml(u.name) + '</div>';
              }).join('');
              coResults.querySelectorAll('.co-pick').forEach(function (row) {
                row.addEventListener('click', function () {
                  var id = parseInt(row.dataset.id, 10);
                  if (!selectedCoauthors.some(function (c) { return c.id === id; })) {
                    selectedCoauthors.push({ id: id, name: row.dataset.name });
                    renderChips();
                  }
                  coSearch.value = ''; coResults.innerHTML = '';
                });
              });
            });
          }, 300);
        });
      }

      /* ---------------- PUBLIER ---------------- */
      document.getElementById('blog-submit').addEventListener('click', function () {
        var submitBtn = this;
        var title   = document.getElementById('blog-title').value.trim();
        var content = document.getElementById('blog-content').value.trim();
        var fb      = document.getElementById('blog-feedback');

        if (!title || !content) {
          fb.style.color = '#A32D2D'; fb.textContent = 'Titre et contenu obligatoires.';
          return;
        }
        if (!imageBase64) {
          fb.style.color = '#A32D2D'; fb.textContent = 'Une photo est obligatoire pour publier.';
          return;
        }

        submitBtn.disabled = true; submitBtn.textContent = '...';

        apiFetch('/blogs/create', {
          method: 'POST',
          body: JSON.stringify({
            title: title,
            content: content,
            image_data: imageBase64,
            coauthors: selectedCoauthors.map(function (c) { return c.id; })
          })
        }).then(function (res) {
          submitBtn.disabled = false; submitBtn.textContent = 'Publier';
          if (res.ok && res.data.success) {
            fb.style.color = '#3B6D11';
            fb.textContent = res.data.pending
              ? 'Blog envoye ! Il sera visible apres validation par l\'administration.'
              : 'Blog publie !';
            document.getElementById('blog-title').value = '';
            document.getElementById('blog-content').value = '';
            document.getElementById('blog-image').value = '';
            document.getElementById('image-preview').style.display = 'none';
            imageBase64 = null;
            selectedCoauthors = []; renderChips();
          } else {
            fb.style.color = '#A32D2D';
            fb.textContent = (res.data && res.data.error) || 'Erreur.';
          }
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
