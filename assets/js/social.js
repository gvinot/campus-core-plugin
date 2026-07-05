// Réseau social Campus — LOT 2 (recherche + sections dédiées)
document.addEventListener('DOMContentLoaded', () => {

    if (typeof CampusData === 'undefined') return;

    const API   = CampusData.apiUrl;
    const NONCE = CampusData.nonce;

    const searchInput   = document.getElementById('campus-search-input');
    const searchResults = document.getElementById('campus-search-results');
    const friendsList   = document.getElementById('campus-friends');
    const requestsList  = document.getElementById('campus-requests');

    if (!searchInput) return; // shortcode absent de la page

    async function apiFetch(endpoint, options = {}) {
        const res = await fetch(`${API}${endpoint}`, {
            credentials: 'include',
            ...options,
            headers: Object.assign(
                { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                options.headers || {}
            )
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function personHTML(p, actionHtml) {
        return `<img src="${p.avatar_url}" alt="">`
             + `<span class="campus-person-name">${escHtml(p.name || p.display_name)}</span>`
             + `<span class="campus-person-action">${actionHtml}</span>`;
    }

    /* ---------------- RECHERCHE ---------------- */
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = searchInput.value.trim();
        if (q.length < 2) { searchResults.innerHTML = ''; return; }
        searchTimer = setTimeout(() => doSearch(q), 300);
    });

    async function doSearch(q) {
        searchResults.innerHTML = '<li class="campus-muted">Recherche…</li>';
        try {
            const data  = await apiFetch('/users/search?q=' + encodeURIComponent(q));
            const users = data.users || [];

            if (!users.length) {
                searchResults.innerHTML = '<li class="campus-muted">Aucun résultat.</li>';
                return;
            }

            searchResults.innerHTML = '';
            users.forEach(u => {
                let action;
                if (u.friendship === 'friends')               action = '<span class="campus-tag">Amis ✓</span>';
                else if (u.friendship === 'pending_sent')     action = '<span class="campus-tag">Demande envoyée</span>';
                else if (u.friendship === 'pending_received') action = `<button class="campus-btn accept" data-id="${u.id}">Accepter</button>`;
                else                                          action = `<button class="campus-btn add" data-id="${u.id}">Ajouter</button>`;

                const li = document.createElement('li');
                li.className = 'campus-person';
                li.innerHTML = personHTML(u, action);
                searchResults.appendChild(li);
            });

            bindSearchButtons();
        } catch (e) {
            searchResults.innerHTML = '<li class="campus-muted">Erreur de recherche.</li>';
        }
    }

    function bindSearchButtons() {
        searchResults.querySelectorAll('.campus-btn.add').forEach(btn => {
            btn.addEventListener('click', async () => {
                btn.disabled = true; btn.textContent = '…';
                try {
                    await apiFetch('/friends/request', { method: 'POST', body: JSON.stringify({ user_id: btn.dataset.id }) });
                    btn.textContent = 'Demande envoyée';
                } catch (e) { btn.disabled = false; btn.textContent = 'Erreur'; }
            });
        });
        searchResults.querySelectorAll('.campus-btn.accept').forEach(btn => {
            btn.addEventListener('click', async () => {
                btn.disabled = true; btn.textContent = '…';
                try {
                    await apiFetch('/friends/accept', { method: 'POST', body: JSON.stringify({ user_id: btn.dataset.id }) });
                    btn.textContent = 'Amis ✓';
                    loadFriends(); loadRequests();
                } catch (e) { btn.disabled = false; btn.textContent = 'Erreur'; }
            });
        });
    }

    /* ---------------- MES AMIS ---------------- */
    async function loadFriends() {
        friendsList.innerHTML = '<li class="campus-muted">Chargement…</li>';
        try {
            const data    = await apiFetch('/friends/list');
            const friends = data.friends || [];

            if (!friends.length) {
                friendsList.innerHTML = '<li class="campus-muted">Aucun ami pour l\'instant.</li>';
                return;
            }

            friendsList.innerHTML = '';
            friends.forEach(f => {
                const li = document.createElement('li');
                li.className = 'campus-person';
                li.innerHTML = personHTML(f, `<button class="campus-btn remove" data-id="${f.friend_id}">Retirer</button>`);
                friendsList.appendChild(li);
            });

            friendsList.querySelectorAll('.campus-btn.remove').forEach(btn => {
                btn.addEventListener('click', async () => {
                    btn.disabled = true; btn.textContent = '…';
                    try {
                        await apiFetch('/friends/remove', { method: 'POST', body: JSON.stringify({ user_id: btn.dataset.id }) });
                        loadFriends();
                    } catch (e) { btn.disabled = false; btn.textContent = 'Erreur'; }
                });
            });
        } catch (e) {
            friendsList.innerHTML = '<li class="campus-muted">Erreur de chargement.</li>';
        }
    }

    /* ---------------- DEMANDES REÇUES ---------------- */
    async function loadRequests() {
        requestsList.innerHTML = '<li class="campus-muted">Chargement…</li>';
        try {
            const data = await apiFetch('/friends/requests');
            const reqs = data.requests || [];

            if (!reqs.length) {
                requestsList.innerHTML = '<li class="campus-muted">Aucune demande.</li>';
                return;
            }

            requestsList.innerHTML = '';
            reqs.forEach(r => {
                const li = document.createElement('li');
                li.className = 'campus-person';
                li.innerHTML = personHTML(r, `<button class="campus-btn accept" data-id="${r.user_id}">Accepter</button>`);
                requestsList.appendChild(li);
            });

            requestsList.querySelectorAll('.campus-btn.accept').forEach(btn => {
                btn.addEventListener('click', async () => {
                    btn.disabled = true; btn.textContent = '…';
                    try {
                        await apiFetch('/friends/accept', { method: 'POST', body: JSON.stringify({ user_id: btn.dataset.id }) });
                        loadFriends(); loadRequests();
                    } catch (e) { btn.disabled = false; btn.textContent = 'Erreur'; }
                });
            });
        } catch (e) {
            requestsList.innerHTML = '<li class="campus-muted">Erreur de chargement.</li>';
        }
    }

    /* ---------------- INIT ---------------- */
    loadFriends();
    loadRequests();
});
