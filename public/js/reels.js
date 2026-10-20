/* ReelsApp front-end: feed (autoplay, like, comments, share, report, mute) + upload form helpers */
(function () {
    'use strict';

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var toastEl = document.getElementById('toast');
    var toastTimer = null;

    function toast(message) {
        if (!toastEl) { return; }
        toastEl.textContent = message;
        toastEl.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
    }

    function api(method, url, body) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (response) {
            if (!response.ok) { throw response; }
            return response.json();
        });
    }

    /* ---------- Splash logo (once per browser session) ---------- */
    var splash = document.getElementById('splash');
    if (splash) {
        var seen = false;
        try { seen = !!sessionStorage.getItem('reels_splash'); sessionStorage.setItem('reels_splash', '1'); } catch (e) {}
        if (seen) {
            splash.remove();
        } else {
            setTimeout(function () {
                splash.classList.add('hide-splash');
                setTimeout(function () { splash.remove(); }, 600);
            }, 1300);
        }
    }

    /* ---------- Upload / edit form helpers ---------- */
    var fileInput = document.getElementById('video-input');
    if (fileInput) {
        var preview = document.getElementById('video-preview');
        var warning = document.getElementById('size-warning');
        var maxBytes = Number(fileInput.dataset.max) || 0;
        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) { preview.classList.add('hide'); return; }
            if (maxBytes && file.size > maxBytes) {
                warning.classList.remove('hide');
                fileInput.value = '';
                preview.classList.add('hide');
                return;
            }
            warning.classList.add('hide');
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hide');
        });
    }
    var captionInput = document.getElementById('caption');
    var captionCount = document.getElementById('caption-count');
    if (captionInput && captionCount) {
        var updateCount = function () { captionCount.textContent = captionInput.value.length + ' / 255'; };
        captionInput.addEventListener('input', updateCount);
        updateCount();
    }

    var videoCategory = document.getElementById('category_id');
    var aiGeneratedInput = document.querySelector('input[name="is_ai_generated"]');
    if (videoCategory && aiGeneratedInput) {
        function selectAiCategory() {
            videoCategory.disabled = aiGeneratedInput.checked;
            if (!aiGeneratedInput.checked) { return; }

            var aiOption = videoCategory.querySelector('option[data-category-slug="ai"]');
            if (aiOption) { videoCategory.value = aiOption.value; }
        }

        aiGeneratedInput.addEventListener('change', selectAiCategory);
        selectAiCategory();
    }

    var categoryTabs = document.querySelector('.chips');
    if (categoryTabs) {
        categoryTabs.addEventListener('wheel', function (event) {
            if (categoryTabs.scrollWidth <= categoryTabs.clientWidth || event.deltaY === 0) { return; }

            var maxScrollLeft = categoryTabs.scrollWidth - categoryTabs.clientWidth;
            if ((event.deltaY > 0 && categoryTabs.scrollLeft < maxScrollLeft)
                || (event.deltaY < 0 && categoryTabs.scrollLeft > 0)) {
                event.preventDefault();
                categoryTabs.scrollLeft += event.deltaY;
            }
        }, { passive: false });
    }

    /* ---------- Feed ---------- */
    var feed = document.getElementById('feed');
    if (!feed) { return; }

    var base = feed.dataset.base;
    var reels = Array.prototype.slice.call(feed.querySelectorAll('.reel'));
    var observer = null;
    var activeReel = null;

    var muted = true;
    try { muted = localStorage.getItem('reels_muted') !== '0'; } catch (e) {}
    var soundBtn = document.getElementById('sound-btn');

    function applyMute() {
        feed.querySelectorAll('video').forEach(function (v) { v.muted = muted; });
        if (soundBtn) {
            soundBtn.classList.toggle('off', muted);
            soundBtn.setAttribute('aria-label', muted ? 'Unmute' : 'Mute');
        }
    }
    if (soundBtn) {
        soundBtn.addEventListener('click', function () {
            muted = !muted;
            try { localStorage.setItem('reels_muted', muted ? '1' : '0'); } catch (e) {}
            applyMute();
        });
    }
    applyMute();

    /* Autoplay the reel that is mostly visible, pause the others */
    observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var reel = entry.target;
            var video = reel.querySelector('video');
            if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
                video.muted = muted;
                var playing = video.play();
                if (playing && playing.catch) { playing.catch(function () {}); }
                reel.classList.remove('paused');
            } else {
                video.pause();
                if (!entry.isIntersecting) { video.currentTime = 0; }
            }
        });
    }, { root: feed, threshold: [0, 0.6, 1] });

    function setupReel(reel) {
        var video = reel.querySelector('video');
        var bar = reel.querySelector('.progress span');
        var fullscreenButton = reel.querySelector('[data-fullscreen]');
        var likeButton = reel.querySelector('[data-act="like"]');
        var likeControl = likeButton.closest('.like-control');
        var lastTap = 0;
        var tapTimer = null;
        var reactionPressTimer = null;
        var longPressTriggered = false;

        function updateVideoOrientation() {
            reel.classList.toggle('landscape-video', video.videoWidth > video.videoHeight);
        }
        video.addEventListener('loadedmetadata', updateVideoOrientation);
        if (video.readyState >= 1) { updateVideoOrientation(); }

        fullscreenButton.addEventListener('click', function (event) {
            event.stopPropagation();
            try {
                if (document.fullscreenElement === reel && document.exitFullscreen) {
                    document.exitFullscreen().catch(function () { toast('Could not exit fullscreen'); });
                } else if (reel.requestFullscreen) {
                    reel.requestFullscreen().catch(function () { toast('Could not enter fullscreen'); });
                } else if (video.webkitEnterFullscreen) {
                    video.webkitEnterFullscreen();
                } else {
                    toast('Fullscreen is not supported by this browser');
                }
            } catch (error) {
                toast('Could not enter fullscreen');
            }
        });

        likeButton.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) { return; }

            longPressTriggered = false;
            clearTimeout(reactionPressTimer);
            reactionPressTimer = setTimeout(function () {
                likeControl.classList.add('reactions-open');
                longPressTriggered = true;
            }, 500);
        });

        function cancelReactionPress() {
            clearTimeout(reactionPressTimer);
        }

        likeButton.addEventListener('pointerup', cancelReactionPress);
        likeButton.addEventListener('pointercancel', cancelReactionPress);
        likeButton.addEventListener('pointerleave', cancelReactionPress);
        likeButton.addEventListener('click', function (event) {
            if (!longPressTriggered) { return; }

            event.preventDefault();
            event.stopPropagation();
            longPressTriggered = false;
        });

        video.addEventListener('timeupdate', function () {
            if (video.duration) { bar.style.width = (video.currentTime / video.duration * 100) + '%'; }
        });

        /* single tap = play/pause, double tap = like */
        reel.querySelector('.tap').addEventListener('click', function () {
            var now = Date.now();
            if (now - lastTap < 280) {
                clearTimeout(tapTimer);
                lastTap = 0;
                popHeart(reel);
                toggleLike(reel, true);
            } else {
                lastTap = now;
                tapTimer = setTimeout(function () {
                    if (video.paused) { video.play(); reel.classList.remove('paused'); }
                    else { video.pause(); reel.classList.add('paused'); }
                }, 280);
            }
        });

        observer.observe(reel);
    }
    reels.forEach(setupReel);

    function popHeart(reel) {
        var heart = reel.querySelector('.pop-heart');
        heart.classList.remove('show');
        void heart.offsetWidth;
        heart.classList.add('show');
    }

    /* ---------- Likes ---------- */
    function setLike(button, liked, count, reaction) {
        button.classList.toggle('liked', liked);
        button.dataset.reaction = reaction || '';
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');
        button.querySelector('.count').textContent = count;

        var likeControl = button.closest('.like-control');
        var selectedOption = null;
        likeControl.querySelectorAll('[data-act="react"]').forEach(function (option) {
            var selected = option.dataset.reaction === reaction;
            option.setAttribute('aria-pressed', selected ? 'true' : 'false');
            if (selected) { selectedOption = option; }
        });

        button.querySelector('.reaction-current').textContent = selectedOption ? selectedOption.dataset.emoji : '';
        button.setAttribute('aria-label', selectedOption
            ? 'Remove ' + selectedOption.getAttribute('aria-label') + ' reaction'
            : 'Like');
    }

    function toggleLike(reel, onlyAdd) {
        var button = reel.querySelector('[data-act="like"]');
        var currentReaction = button.dataset.reaction || '';
        var count = parseInt(button.querySelector('.count').textContent, 10) || 0;
        if (onlyAdd && currentReaction) { return; }

        updateReaction(reel, button, currentReaction === 'like' ? null : 'like', count);
    }

    function chooseReaction(reel, reaction) {
        var button = reel.querySelector('[data-act="like"]');
        var currentReaction = button.dataset.reaction || '';
        var count = parseInt(button.querySelector('.count').textContent, 10) || 0;

        updateReaction(reel, button, currentReaction === reaction ? null : reaction, count);
    }

    function updateReaction(reel, button, nextReaction, count) {
        var previousReaction = button.dataset.reaction || '';
        var nextCount = count + (previousReaction === '' && nextReaction ? 1 : (previousReaction && !nextReaction ? -1 : 0));
        setLike(button, !!nextReaction, Math.max(0, nextCount), nextReaction);

        api('POST', base + '/' + reel.dataset.id + '/like', { reaction: nextReaction }).then(function (data) {
            setLike(button, data.liked, data.count, data.reaction);
        }).catch(function () {
            setLike(button, !!previousReaction, count, previousReaction);
            toast('Could not update reaction');
        });
    }

    /* ---------- Sheets ---------- */
    var backdrop = document.getElementById('backdrop');
    var commentsSheet = document.getElementById('comments-sheet');
    var moreSheet = document.getElementById('more-sheet');
    var reportSheet = document.getElementById('report-sheet');

    function openSheet(sheet) { backdrop.classList.add('open'); sheet.classList.add('open'); }
    function closeSheets() {
        backdrop.classList.remove('open');
        commentsSheet.classList.remove('open');
        moreSheet.classList.remove('open');
        reportSheet.classList.remove('open');
    }
    backdrop.addEventListener('click', closeSheets);
    document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closeSheets); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeSheets(); }
        if (event.key === 'Escape') {
            feed.querySelectorAll('.like-control.reactions-open').forEach(function (likeControl) {
                likeControl.classList.remove('reactions-open');
            });
        }
        if (event.target.isContentEditable || /INPUT|TEXTAREA|SELECT/.test(event.target.tagName)) { return; }
        if (event.key === 'ArrowDown') { feed.scrollBy({ top: feed.clientHeight, behavior: 'smooth' }); event.preventDefault(); }
        if (event.key === 'ArrowUp') { feed.scrollBy({ top: -feed.clientHeight, behavior: 'smooth' }); event.preventDefault(); }
    });

    /* ---------- Comments ---------- */
    var commentsList = document.getElementById('comments-list');
    var commentForm = document.getElementById('comment-form');
    var commentInput = document.getElementById('comment-input');

    function setCommentCount(reel, count) {
        if (!reel) { return; }
        reel.querySelector('[data-act="comment"] .count').textContent = count;
    }

    function commentNode(comment) {
        var row = document.createElement('div');
        row.className = 'comment';
        var avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.textContent = comment.initial;
        var body = document.createElement('div');
        body.className = 'comment-body';
        var name = document.createElement('strong');
        name.textContent = comment.user;
        var time = document.createElement('span');
        time.className = 'muted small';
        time.textContent = ' ' + comment.ago;
        var text = document.createElement('p');
        text.textContent = comment.body;
        body.appendChild(name);
        body.appendChild(time);
        body.appendChild(text);
        if (comment.mine) {
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'link-btn';
            del.textContent = 'Delete';
            del.addEventListener('click', function () {
                api('DELETE', base + '/comments/' + comment.id).then(function (data) {
                    row.remove();
                    setCommentCount(activeReel, data.count);
                    if (!commentsList.children.length) { renderComments([]); }
                }).catch(function () { toast('Could not delete comment'); });
            });
            body.appendChild(del);
        }
        row.appendChild(avatar);
        row.appendChild(body);
        return row;
    }

    function renderComments(list) {
        commentsList.textContent = '';
        if (!list.length) {
            var empty = document.createElement('p');
            empty.className = 'muted center empty-comments';
            empty.textContent = 'No comments yet. Be the first!';
            commentsList.appendChild(empty);
            return;
        }
        list.forEach(function (comment) { commentsList.appendChild(commentNode(comment)); });
    }

    function openComments(reel) {
        activeReel = reel;
        commentsList.textContent = 'Loading...';
        openSheet(commentsSheet);
        api('GET', base + '/' + reel.dataset.id + '/comments').then(renderComments)
            .catch(function () { commentsList.textContent = 'Could not load comments.'; });
    }

    commentForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = commentInput.value.trim();
        if (!text || !activeReel) { return; }
        api('POST', base + '/' + activeReel.dataset.id + '/comments', { body: text }).then(function (data) {
            var empty = commentsList.querySelector('.empty-comments');
            if (empty) { empty.remove(); }
            commentsList.insertBefore(commentNode(data.comment), commentsList.firstChild);
            commentInput.value = '';
            setCommentCount(activeReel, data.count);
        }).catch(function () { toast('Could not post comment'); });
    });

    /* ---------- Share ---------- */
    function share(reel) {
        var url = base + '?v=' + reel.dataset.id;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () { toast('Link copied'); },
                function () { window.prompt('Copy this link', url); });
        } else {
            window.prompt('Copy this link', url);
        }
    }

    /* ---------- More options: edit / report AI / not interested ---------- */
    var moreEdit = document.getElementById('more-edit');
    var moreReport = document.getElementById('more-report');
    var moreMute = document.getElementById('more-mute');

    function openReport(reel) {
        activeReel = reel;
        document.querySelectorAll('#report-form input[name="reason"]').forEach(function (input) {
            input.checked = false;
        });
        openSheet(reportSheet);
    }

    var reportForm = document.getElementById('report-form');
    var reportSubmit = document.getElementById('report-submit');
    reportForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!activeReel) { return; }

        var selectedReason = reportForm.querySelector('input[name="reason"]:checked');
        if (!selectedReason) { return; }

        var reel = activeReel;
        reportSubmit.disabled = true;
        api('POST', base + '/' + reel.dataset.id + '/report', { reason: selectedReason.value }).then(function (data) {
            closeSheets();
            if (data.labelled) {
                reel.dataset.ai = '1';
                var creatorReportButton = reel.querySelector('[data-act="report"]');
                if (creatorReportButton) { creatorReportButton.remove(); }
                toast('Thanks! This video is now labelled as AI');
            } else {
                toast('Thanks, your report has been submitted');
            }
        }).catch(function () {
            toast('Could not submit report');
        }).finally(function () {
            reportSubmit.disabled = false;
        });
    });

    function openMore(reel) {
        activeReel = reel;
        var mine = reel.dataset.mine === '1';
        moreEdit.classList.toggle('hide', !mine);
        if (mine) { moreEdit.href = reel.dataset.edit; }
        moreReport.classList.toggle('hide', mine || reel.dataset.ai === '1');
        moreMute.classList.toggle('hide', mine);
        moreMute.textContent = 'Not interested in ' + reel.dataset.creator;
        openSheet(moreSheet);
    }

    moreReport.addEventListener('click', function () {
        if (!activeReel) { return; }
        openReport(activeReel);
    });

    moreMute.addEventListener('click', function () {
        if (!activeReel) { return; }
        var userId = activeReel.dataset.user;
        api('POST', base + '/mute/' + userId).then(function () {
            closeSheets();
            feed.querySelectorAll('.reel[data-user="' + userId + '"]').forEach(function (reel) {
                observer.unobserve(reel);
                reel.remove();
            });
            toast('You will see fewer videos from this creator');
            if (!feed.querySelector('.reel')) { window.location.reload(); }
        }).catch(function () { toast('Could not update'); });
    });

    /* ---------- Rail buttons (event delegation) ---------- */
    feed.addEventListener('click', function (event) {
        var openReactionControl = feed.querySelector('.like-control.reactions-open');
        if (openReactionControl && !event.target.closest('.like-control.reactions-open')) {
            openReactionControl.classList.remove('reactions-open');
        }

        var button = event.target.closest('[data-act]');
        if (!button) { return; }
        var reel = button.closest('.reel');
        var act = button.dataset.act;
        if (act === 'like') {
            button.closest('.like-control').classList.remove('reactions-open');
            toggleLike(reel, false);
        }
        if (act === 'react') {
            button.closest('.like-control').classList.remove('reactions-open');
            chooseReaction(reel, button.dataset.reaction);
        }
        if (act === 'comment') { openComments(reel); }
        if (act === 'share') { share(reel); }
        if (act === 'favorite') {
            var favorited = button.getAttribute('aria-pressed') === 'true';
            button.disabled = true;
            api(favorited ? 'DELETE' : 'POST', favorited ? reel.dataset.unfavoriteUrl : reel.dataset.favoriteUrl)
                .then(function (data) {
                    button.setAttribute('aria-pressed', data.favorited ? 'true' : 'false');
                    button.classList.toggle('favorited', data.favorited);
                    button.setAttribute('aria-label', data.favorited ? 'Remove from favorites' : 'Add to favorites');
                    button.querySelector('span:last-child').textContent = data.favorited ? 'Favorited' : 'Favorite';
                }).catch(function () {
                    toast('Could not update favorite');
                }).finally(function () {
                    button.disabled = false;
                });
        }
        if (act === 'more') { openMore(reel); }
        if (act === 'report') { openReport(reel); }
        if (act === 'follow') {
            var following = button.getAttribute('aria-pressed') === 'true';
            button.disabled = true;
            api(following ? 'DELETE' : 'POST', following ? reel.dataset.unfollowUrl : reel.dataset.followUrl)
                .then(function (data) {
                    button.setAttribute('aria-pressed', data.following ? 'true' : 'false');
                    button.classList.toggle('following', data.following);
                    button.textContent = data.following ? 'Following' : 'Follow';
                }).catch(function () {
                    toast('Could not update follow');
                }).finally(function () {
                    button.disabled = false;
                });
        }
    });

    /* ---------- AI filter: Show -> Less -> Hide ---------- */
    var aiPill = document.getElementById('ai-pill');
    if (aiPill) {
        aiPill.addEventListener('click', function () {
            var order = ['show', 'less', 'hide'];
            var next = order[(order.indexOf(aiPill.dataset.mode) + 1) % order.length];
            api('POST', base + '/preference', { ai_content_mode: next })
                .then(function () { window.location.reload(); })
                .catch(function () { toast('Could not save setting'); });
        });
    }

    /* ---------- Jump to a shared reel (?v=ID) ---------- */
    var match = window.location.search.match(/[?&]v=(\d+)/);
    if (match) {
        var target = document.getElementById('reel-' + match[1]);
        if (target) { target.scrollIntoView(); }
    }
})();
