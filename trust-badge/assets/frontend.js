(function(window, document, $){
    'use strict';

    if (!window.TBFrontend) {
        return;
    }

    var restRoot = window.TBFrontend.restRoot.replace(/\/$/, '');
    var nonce    = window.TBFrontend.nonce;
    var modalRoot = document.getElementById('tb-modal-root');
    var embedScriptUrl = window.TBFrontend.embedScript || '';
    var iframeBase = window.TBFrontend.iframeBase || '';

    if (iframeBase && iframeBase.slice(-1) !== '/') {
        iframeBase += '/';
    }

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
            '<div class="tb-embed__group">' +
                '<label>' + wp.i18n.__('Script embed', 'trust-badge') + '</label>' +
                '<textarea class="tb-embed__code" data-type="script" readonly></textarea>' +
                '<button type="button" class="button button-secondary tb-copy" data-target="script">' + window.TBFrontend.copyLabel + '</button>' +
            '</div>' +
            '<div class="tb-embed__group">' +
                '<label>' + wp.i18n.__('Iframe fallback', 'trust-badge') + '</label>' +
                '<textarea class="tb-embed__code tb-embed__code--compact" data-type="iframe" readonly></textarea>' +
                '<button type="button" class="button button-secondary tb-copy" data-target="iframe">' + wp.i18n.__('Copy iframe code', 'trust-badge') + '</button>' +
                '<p class="tb-embed__hint">' + wp.i18n.__('Use this option if script embeds are not supported by your site builder.', 'trust-badge') + '</p>' +
            '</div>' +
            '<div class="tb-embed__meta"></div></div>';

        openModal(overlay);

        fetch(restRoot + '/requests/' + options.requestId, { headers: { 'X-WP-Nonce': nonce } })
            .then(function(res){ return res.ok ? res.json() : Promise.reject(); })
            .then(function(data){
                var scriptField = container.querySelector('.tb-embed__code[data-type="script"]');
                var iframeField = container.querySelector('.tb-embed__code[data-type="iframe"]');
                var token = data.token;
                var scriptUrl = embedScriptUrl || (window.location.origin + '/wp-content/plugins/trust-badge/assets/embed.js');
                var iframeUrlBase = iframeBase || (restRoot.replace(/\/$/, '') + '/embed/');
                var scriptSnippet = '<div class="tb-trust-badge" data-tb-token="' + token + '"></div>\n<script async src="' + scriptUrl + '"></script>';
                var iframeSnippet = '<iframe src="' + iframeUrlBase + token + '" width="320" height="140" loading="lazy" frameborder="0" referrerpolicy="no-referrer-when-downgrade"></iframe>';

                if (scriptField) {
                    scriptField.value = scriptSnippet;
                }

                if (iframeField) {
                    iframeField.value = iframeSnippet;
                }

                var meta = container.querySelector('.tb-embed__meta');
                if (data.expires_at){
                    meta.textContent = wp.i18n.sprintf(wp.i18n.__('Valid until %s', 'trust-badge'), new Date(data.expires_at).toLocaleDateString());
                }
            });

        container.querySelectorAll('.tb-copy').forEach(function(button){
            button.addEventListener('click', function(){
                var target = button.getAttribute('data-target');
                var textarea = container.querySelector('.tb-embed__code[data-type="' + target + '"]');

                if (!textarea){
                    return;
                }

                var value = textarea.value;
                var originalText = button.textContent;

                var markCopied = function(){
                    button.textContent = window.TBFrontend.copiedLabel;
                    button.disabled = true;
                    setTimeout(function(){
                        button.textContent = originalText;
                        button.disabled = false;
                    }, 2000);
                };

                var promptFallback = function(){
                    window.prompt(wp.i18n.__('Copy this code manually:', 'trust-badge'), value);
                };

                if (navigator.clipboard && navigator.clipboard.writeText){
                    navigator.clipboard.writeText(value).then(markCopied).catch(function(){
                        textarea.select();
                        try {
                            document.execCommand('copy');
                            markCopied();
                        } catch (err) {
                            promptFallback();
                        }
                    });
                    return;
                }

                textarea.select();

                try {
                    document.execCommand('copy');
                    markCopied();
                } catch (err) {
                    promptFallback();
                }
            });
        });
    }

    document.addEventListener('click', function(event){
        var trigger = event.target.closest('a.tb-listing-action');

        if (!trigger){
            return;
        }

        var dataset = trigger.dataset || {};
        var actionType = dataset.tbAction;

        if (!actionType){
            return;
        }

        event.preventDefault();

        var args = {};

        if (dataset.listingId){
            args.listingId = parseInt(dataset.listingId, 10);
        }

        if (dataset.requestId){
            args.requestId = parseInt(dataset.requestId, 10);
        }

        if (dataset.tbRenew){
            args.renew = dataset.tbRenew !== '0';
        }

        if (actionType === 'request'){
            renderRequestForm(args);
            return;
        }

        if (actionType === 'embed' && args.requestId){
            renderEmbedModal(args);
        }
    });

    window.tbOpenRequest = function(args){
        renderRequestForm(args || {});
    };

    window.tbOpenEmbed = function(args){
        renderEmbedModal(args || {});
    };

})(window, document, window.jQuery || {});
