// Formulaire de publication de blog (bio + blog + co-auteurs) — fichier plugin
(function () {
  function init() {
    if (!document.getElementById('campus-blog-tools')) return;

    var imageBase64 = null;
    var selectedCoauthors = []; // {id, name}

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
    function escHtml(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

    function renderChips() {
      var wrap = document.getElementById('coauthor-chips');
      if (!wrap) return;
      wrap.innerHTML = selectedCoauthors.map(function (c) {
        return '<span style="display:inline-flex;align-items:center;gap:6px;background:#1B3A5C;color:#fff;border-radius:20px;padding:4px 12px;font-size:13px;margin:0 6px 6px 0;">'
          + escHtml(c.name)
          + '<span data-id="' + c.id + '" class="chip-remove" style="cursor:pointer;font-weight:700;">×</span>'
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

      apiFetch('/users/me').then(function (res) {
        var me = res.data.user;
        if (me.bio) {
          document.getElementById('bio-text').value = me.bio;
          document.getElementById('bio-count').textContent = me.bio.length + ' / 500';
        }
        if (me.role === 'blogger' || me.role === 'admin') {
          document.getElementById('blog-publish-card').style.display = 'block';
        }
      });

      // Bio
      var bioText = document.getElementById('bio-text');
      bioText.addEventListener('input', function () {
        document.getElementById('bio-count').textContent = bioText.value.length + ' / 500';
      });
      document.getElementById('bio-save').addEventListener('click', function () {
        var btn = this, fb = document.getElementById('bio-feedback');
        btn.disabled = true; btn.textContent = '...';
        apiFetch('/users/me/bio', { method: 'POST', body: JSON.stringify({ bio: bioText.value }) }).then(function (res) {
          btn.disabled = false; btn.textContent = 'Enregistrer';
          if (res.ok) { fb.style.color = '#3B6D11'; fb.textContent = 'Bio enregistree !'; }
          else { fb.style.color = '#A32D2D'; fb.textContent = res.data.error || 'Erreur.'; }
          setTimeout(function () { fb.textContent = ''; }, 3000);
        });
      });

      // Image
      var imgInput = document.getElementById('blog-image');
      imgInput.addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) { imageBase64 = null; document.getElementById('image-preview').style.display = 'none'; return; }
        if (file.size > 5 * 1024 * 1024) {
          document.getElementById('blog-feedback').style.color = '#A32D2D';
          document.getElementById('blog-feedback').textContent = 'Image trop lourde (max 5 Mo).';
          imgInput.value = ''; imageBase64 = null; return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
          imageBase64 = ev.target.result;
          document.getElementById('preview-img').src = imageBase64;
          document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
      });

      // Recherche de co-auteurs (parmi les amis)
      var coSearch = document.getElementById('coauthor-search');
      var coResults = document.getElementById('coauthor-results');
      var coTimer;
      if (coSearch) {
        coSearch.addEventListener('input', function () {
          clearTimeout(coTimer);
          var q = coSearch.value.trim();
          if (q.length < 2) { coResults.innerHTML = ''; return; }
          coTimer = setTimeout(function () {
            apiFetch('/users/search?q=' + encodeURIComponent(q)).then(function (res) {
              var users = (res.data.users || []).filter(function (u) { return u.friendship === 'friends'; });
              if (!users.length) { coResults.innerHTML = '<div style="color:#999;font-size:13px;padding:6px;">Aucun ami trouvé.</div>'; return; }
              coResults.innerHTML = users.map(function (u) {
                return '<div class="co-pick" data-id="' + u.id + '" data-name="' + escHtml(u.name) + '" style="padding:8px;cursor:pointer;border-radius:6px;display:flex;align-items:center;gap:8px;">'
                  + '<img src="' + u.avatar_url + '" style="width:24px;height:24px;border-radius:50%;">' + escHtml(u.name) + '</div>';
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

      // Publier
      document.getElementById('blog-submit').addEventListener('click', function () {
        var submitBtn = this;
        var title = document.getElementById('blog-title').value.trim();
        var content = document.getElementById('blog-content').value.trim();
        var fb = document.getElementById('blog-feedback');

        if (!title || !content) { fb.style.color = '#A32D2D'; fb.textContent = 'Titre et contenu obligatoires.'; return; }
        if (!imageBase64) { fb.style.color = '#A32D2D'; fb.textContent = 'Une photo est obligatoire pour publier.'; return; }

        submitBtn.disabled = true; submitBtn.textContent = '...';
        var payload = {
          title: title,
          content: content,
          image_data: imageBase64,
          coauthors: selectedCoauthors.map(function (c) { return c.id; })
        };
        apiFetch('/blogs/create', { method: 'POST', body: JSON.stringify(payload) }).then(function (res) {
          submitBtn.disabled = false; submitBtn.textContent = 'Publier';
          if (res.ok && res.data.success) {
            fb.style.color = '#3B6D11'; 
            fb.textContent = res.data.pending
                ? 'Blog envoyé ! Il sera visible après validation par l\'administration.'
                : 'Blog publié !';
            document.getElementById('blog-title').value = '';
            document.getElementById('blog-content').value = '';
            document.getElementById('blog-image').value = '';
            document.getElementById('image-preview').style.display = 'none';
            imageBase64 = null;
            selectedCoauthors = []; renderChips();
          } else {
            fb.style.color = '#A32D2D'; fb.textContent = res.data.error || 'Erreur.';
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
