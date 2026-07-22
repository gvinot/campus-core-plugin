// Page "Mon compte" — photo de profil + bio
// Chaque bloc est indépendant : le script ne fait rien si son conteneur est absent.
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

  function init() {
    var avatarBox = document.getElementById('account-avatar');
    var bioBox    = document.getElementById('account-bio');
    if (!avatarBox && !bioBox) return; // pas sur la page Mon compte

    waitForData(function () {

      apiFetch('/users/me').then(function (res) {
        var me = res.data && res.data.user;
        if (!me) return;

        if (avatarBox) {
          var img = document.getElementById('avatar-current');
          if (img) img.src = me.avatar_url;
        }
        if (bioBox && me.bio) {
          var t = document.getElementById('bio-text');
          if (t) {
            t.value = me.bio;
            document.getElementById('bio-count').textContent = me.bio.length + ' / 500';
          }
        }
      });

      /* ---------------- PHOTO DE PROFIL ---------------- */
      if (avatarBox) {
        var avatarBase64 = null;
        var input   = document.getElementById('avatar-input');
        var saveBtn = document.getElementById('avatar-save');

        input.addEventListener('change', function (e) {
          var file = e.target.files[0];
          var fb   = document.getElementById('avatar-feedback');
          if (!file) { avatarBase64 = null; saveBtn.disabled = true; return; }
          if (file.size > 5 * 1024 * 1024) {
            fb.style.color = '#A32D2D';
            fb.textContent = 'Image trop lourde (max 5 Mo).';
            input.value = ''; avatarBase64 = null; saveBtn.disabled = true;
            return;
          }
          var reader = new FileReader();
          reader.onload = function (ev) {
            avatarBase64 = ev.target.result;
            document.getElementById('avatar-current').src = avatarBase64; // aperçu
            saveBtn.disabled = false;
          };
          reader.readAsDataURL(file);
        });

        saveBtn.addEventListener('click', function () {
          if (!avatarBase64) return;
          var fb = document.getElementById('avatar-feedback');
          saveBtn.disabled = true; saveBtn.textContent = '...';
          apiFetch('/users/me/avatar', {
            method: 'POST',
            body: JSON.stringify({ image_data: avatarBase64 })
          }).then(function (res) {
            saveBtn.textContent = 'Enregistrer la photo';
            if (res.ok && res.data.success) {
              fb.style.color = '#3B6D11';
              fb.textContent = 'Photo mise a jour !';
              document.getElementById('avatar-current').src = res.data.avatar_url;
            } else {
              saveBtn.disabled = false;
              fb.style.color = '#A32D2D';
              fb.textContent = (res.data && res.data.error) || 'Erreur.';
            }
            setTimeout(function () { fb.textContent = ''; }, 3000);
          });
        });
      }

      /* ---------------- BIO ---------------- */
      if (bioBox) {
        var bioText = document.getElementById('bio-text');
        var bioSave = document.getElementById('bio-save');

        bioText.addEventListener('input', function () {
          document.getElementById('bio-count').textContent = bioText.value.length + ' / 500';
        });

        bioSave.addEventListener('click', function () {
          var fb = document.getElementById('bio-feedback');
          bioSave.disabled = true; bioSave.textContent = '...';
          apiFetch('/users/me/bio', {
            method: 'POST',
            body: JSON.stringify({ bio: bioText.value })
          }).then(function (res) {
            bioSave.disabled = false; bioSave.textContent = 'Enregistrer';
            if (res.ok) { fb.style.color = '#3B6D11'; fb.textContent = 'Bio enregistree !'; }
            else { fb.style.color = '#A32D2D'; fb.textContent = (res.data && res.data.error) || 'Erreur.'; }
            setTimeout(function () { fb.textContent = ''; }, 3000);
          });
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
