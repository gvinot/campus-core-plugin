// Bouton "J'aime" sur la page d'un blog — LOT 10
document.addEventListener('DOMContentLoaded', function () {

    var btn = document.querySelector('.campus-like-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {

        // Invité (non connecté) → rediriger vers la connexion
        if (btn.dataset.guest === '1' || typeof CampusData === 'undefined') {
            window.location.href = '/login';
            return;
        }

        btn.disabled = true;
        var postId = btn.dataset.post;

        fetch(CampusData.apiUrl + '/blogs/' + postId + '/like', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': CampusData.nonce
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (!data.success) return;

            var liked = data.liked;
            btn.dataset.liked = liked ? '1' : '0';
            btn.style.background = liked ? '#E8621A' : '#fff';
            btn.style.color      = liked ? '#fff'    : '#E8621A';
            btn.querySelector('.campus-like-heart').textContent = liked ? '❤️' : '🤍';
            btn.querySelector('.campus-like-count').textContent = data.like_count;
            btn.querySelector('.campus-like-label').textContent = liked ? 'Aimé' : "J'aime";
        })
        .catch(function () {
            btn.disabled = false;
        });
    });
});
