// Affichage des badges — s'insère dans un conteneur #campus-badges
// Le conteneur doit avoir un attribut data-user-id (ou lit ?id= sur la page Membre, ou /users/me).
(function () {
  function init() {
    var box = document.getElementById('campus-badges');
    if (!box) return;

    function waitForData(cb) {
      if (window.CampusData && CampusData.nonce) return cb();
      setTimeout(function () { waitForData(cb); }, 100);
    }
    function apiFetch(endpoint) {
      return fetch(CampusData.apiUrl + endpoint, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CampusData.nonce }
      }).then(function (r) { return r.json(); });
    }
    function escHtml(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function getParam(name){
      var m = new RegExp('[?&]' + name + '=([^&]+)').exec(window.location.search);
      return m ? decodeURIComponent(m[1]) : null;
    }

    function render(badges) {
      if (!badges.length) {
        box.innerHTML = '<p style="color:#999;font-size:14px;">Aucun badge pour le moment. Publie, partage et commente pour en gagner !</p>';
        return;
      }
      box.innerHTML = badges.map(function (b) {
        return '<div title="' + escHtml(b.description) + '" style="display:flex;align-items:center;gap:10px;background:#fff;border:1.5px solid #E8621A;border-radius:30px;padding:8px 16px;">'
          + '<span style="font-size:20px;">' + b.icon + '</span>'
          + '<div><div style="color:#1B3A5C;font-weight:600;font-size:13px;">' + escHtml(b.name) + '</div>'
          + '<div style="color:#888;font-size:11px;">' + escHtml(b.description) + '</div></div>'
          + '</div>';
      }).join('');
    }

    waitForData(function () {
      // Déterminer de quel utilisateur afficher les badges
      var targetId = box.getAttribute('data-user-id');

      function loadFor(id) {
        apiFetch('/users/' + id + '/badges').then(function (data) {
          render(data.badges || []);
        }).catch(function () {
          box.innerHTML = '<p style="color:#999;">Impossible de charger les badges.</p>';
        });
      }

      if (targetId) {
        loadFor(targetId);
      } else if (getParam('id')) {
        loadFor(parseInt(getParam('id'), 10)); // page Membre
      } else {
        apiFetch('/users/me').then(function (res) { // Mon Profil
          if (res && res.user) loadFor(res.user.id);
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
