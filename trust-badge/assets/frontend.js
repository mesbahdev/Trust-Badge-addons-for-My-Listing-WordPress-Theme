(function(window, document, $){
    'use strict';

    if (!window.TBFrontend) {
        return;
    }

    var restRoot = window.TBFrontend.restRoot.replace(/\/$/, '');
    var nonce    = window.TBFrontend.nonce;
    var modalRoot = document.getElementById('tb-modal-root');

    function ensureModalRoot(){
        if (!modalRoot) {
            modalRoot = document.createElement('div');
            modalRoot.id = 'tb-modal-root';
            modalRoot.className = 'tb-hidden';
            document.body.appendChild(modalRoot);
        }
    }

    function closeModal(){
        ensureModalRoot();
        modalRoot.innerHTML = '';
        modalRoot.classList.add('tb-hidden');
        modalRoot.removeAttribute('aria-hidden');
    }

    function openModal(content){
        ensureModalRoot();
        modalRoot.innerHTML = '';
        modalRoot.appendChild(content);
        modalRoot.classList.remove('tb-hidden');
        modalRoot.setAttribute('aria-hidden', 'false');
    }

    function createOverlay(){
        var overlay = document.createElement('div');
        overlay.className = 'tb-modal-overlay';
        overlay.innerHTML = '<div class="tb-modal" role="dialog" aria-modal="true"><button type="button" class="tb-modal__close" aria-label="Close">&times;</button><div class="tb-modal__content"></div></div>';
        overlay.querySelector('.tb-modal__close').addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e){
            if (e.target === overlay) {
                closeModal();
            }
        });
        return overlay;
    }

    function renderRequestForm(options){
        options = options || {};
        var overlay = createOverlay();
        var container = overlay.querySelector('.tb-modal__content');
        container.innerHTML = '<h2>' + (options.renew ? window.tb_i18n_renew_title || 'Renew Trust Badge' : 'Trust Badge Request') + '</h2>' +
            '<form class="tb-request-form">' +
            '<label>' + wp.i18n.__('Contact name', 'trust-badge') + '<input type="text" name="contact_name" required></label>' +
            '<label>' + wp.i18n.__('Contact email', 'trust-badge') + '<input type="email" name="contact_email" required></label>' +
            '<label>' + wp.i18n.__('Contact phone', 'trust-badge') + '<input type="text" name="contact_phone"></label>' +
            '<div class="tb-upload-list"><strong>' + wp.i18n.__('Documents', 'trust-badge') + '</strong><ul></ul><button type="button" class="button tb-upload-button">' + wp.i18n.__('Add documents', 'trust-badge') + '</button></div>' +
            '<label class="tb-consent"><input type="checkbox" name="consent" required> ' + wp.i18n.__('I confirm documents are accurate', 'trust-badge') + '</label>' +
            '<div class="tb-actions"><button type="submit" class="button button-primary">' + wp.i18n.__('Submit request', 'trust-badge') + '</button></div>' +
            '<div class="tb-response" aria-live="polite"></div>' +
            '</form>';

        var form = container.querySelector('form');
        var docList = form.querySelector('.tb-upload-list ul');
        var documents = [];

        function renderDocuments(){
            docList.innerHTML = '';
            documents.forEach(function(doc){
                var li = document.createElement('li');
                li.innerHTML = '<span>' + doc.title + '</span> <button type="button" class="tb-remove" data-id="' + doc.id + '">&times;</button>';
                docList.appendChild(li);
            });
        }

        function openMedia(){
            if (!wp.media) {
                window.alert('Media library unavailable');
                return;
            }
            var frame = wp.media({
                title: wp.i18n.__('Select documents', 'trust-badge'),
                multiple: true,
                library: { type: 'application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document' }
            });
            frame.on('select', function(){
                var selection = frame.state().get('selection');
                selection.each(function(attachment){
                    var data = attachment.toJSON();
                    documents.push({ id: data.id, title: data.title });
                });
                renderDocuments();
            });
            frame.open();
        }

        form.querySelector('.tb-upload-button').addEventListener('click', function(e){
            e.preventDefault();
            openMedia();
        });

        docList.addEventListener('click', function(e){
            if (e.target.classList.contains('tb-remove')){
                var removeId = parseInt(e.target.getAttribute('data-id'), 10);
                documents = documents.filter(function(doc){ return doc.id !== removeId; });
                renderDocuments();
            }
        });

        if (options.requestId){
            fetch(restRoot + '/requests/' + options.requestId, { headers: { 'X-WP-Nonce': nonce } })
                .then(function(res){ return res.ok ? res.json() : Promise.reject(); })
                .then(function(data){
                    form.querySelector('[name="contact_name"]').value = data.contact.name || '';
                    form.querySelector('[name="contact_email"]').value = data.contact.email || '';
                    form.querySelector('[name="contact_phone"]').value = data.contact.phone || '';
                    documents = (data.documents || []).map(function(id){
                        return { id: id, title: wp.i18n.sprintf(wp.i18n.__('Attachment #%d', 'trust-badge'), id) };
                    });
                    renderDocuments();
                })
                .catch(function(){ /* ignore */ });
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            var formData = new window.FormData(form);
            if (!formData.get('consent')){
                window.alert(wp.i18n.__('You must consent before submitting.', 'trust-badge'));
                return;
            }
            var payload = {
                listing_id: options.listingId,
                contact_name: formData.get('contact_name'),
                contact_email: formData.get('contact_email'),
                contact_phone: formData.get('contact_phone'),
                documents: documents.map(function(doc){ return doc.id; })
            };
            fetch(restRoot + '/requests', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            }).then(function(res){
                if (!res.ok){
                    return res.json().then(function(error){ throw error; });
                }
                return res.json();
            }).then(function(){
                form.querySelector('.tb-response').textContent = wp.i18n.__('Request submitted successfully.', 'trust-badge');
                setTimeout(closeModal, 1500);
            }).catch(function(error){
                var message = (error && error.message) ? error.message : wp.i18n.__('Unable to submit request.', 'trust-badge');
                form.querySelector('.tb-response').textContent = message;
            });
        });

        openModal(overlay);
        form.querySelector('[name="contact_name"]').focus();
    }

    function renderEmbedModal(options){
        var overlay = createOverlay();
        var container = overlay.querySelector('.tb-modal__content');
        container.innerHTML = '<h2>' + wp.i18n.__('Trust Badge Embed', 'trust-badge') + '</h2>' +
            '<div class="tb-embed"><p>' + wp.i18n.__('Use the code below to embed your live trust badge.', 'trust-badge') + '</p>' +
            '<textarea class="tb-embed__code" readonly></textarea>' +
            '<button type="button" class="button button-secondary tb-copy">' + window.TBFrontend.copyLabel + '</button>' +
            '<div class="tb-embed__meta"></div></div>';

        openModal(overlay);

        fetch(restRoot + '/requests/' + options.requestId, { headers: { 'X-WP-Nonce': nonce } })
            .then(function(res){ return res.ok ? res.json() : Promise.reject(); })
            .then(function(data){
                var textarea = container.querySelector('.tb-embed__code');
                var token = data.token;
                var origin = window.location.origin;
                var snippet = '<div data-tb-token="' + token + '"></div>\n<script async src="' + origin + '/wp-content/plugins/trust-badge/assets/embed.js"></script>';
                textarea.value = snippet;
                var meta = container.querySelector('.tb-embed__meta');
                if (data.expires_at){
                    meta.textContent = wp.i18n.sprintf(wp.i18n.__('Valid until %s', 'trust-badge'), new Date(data.expires_at).toLocaleDateString());
                }
            });

        container.querySelector('.tb-copy').addEventListener('click', function(){
            var textarea = container.querySelector('.tb-embed__code');
            textarea.select();
            try {
                document.execCommand('copy');
                this.textContent = window.TBFrontend.copiedLabel;
            } catch (e) {
                window.prompt('Copy code:', textarea.value);
            }
        });
    }

    window.tbOpenRequest = function(args){
        renderRequestForm(args || {});
    };

    window.tbOpenEmbed = function(args){
        renderEmbedModal(args || {});
    };

})(window, document, window.jQuery || {});
