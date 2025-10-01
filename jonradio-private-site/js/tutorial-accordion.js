(function(){
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function getStorage() {
        try {
            return window.localStorage || null;
        } catch (err) {
            return null;
        }
    }

    ready(function(){
        var accordions = document.querySelectorAll('.jrps-video-accordion');
        if (!accordions.length) {
            return;
        }

        accordions.forEach(function(accordion){
            var toggle = accordion.querySelector('.jrps-accordion-toggle');
            var panel  = accordion.querySelector('.jrps-accordion-panel');
            if (!toggle || !panel) {
                return;
            }

            var storageKey = accordion.getAttribute('data-storage-key') || 'jrps_public_pages_tutorial';
            var storage    = getStorage();
            var expanded   = accordion.classList.contains('jrps-accordion-open');
            if (!expanded) {
                expanded = !accordion.classList.contains('jrps-accordion-closed');
            }

            if (storage) {
                var stored = storage.getItem(storageKey);
                if (stored === 'closed') {
                    expanded = false;
                }
            }

            setState(expanded);

            toggle.addEventListener('click', function(){
                expanded = !expanded;
                setState(expanded);
                if (storage) {
                    try {
                        storage.setItem(storageKey, expanded ? 'open' : 'closed');
                    } catch (err) {
                        // Ignore storage errors (quota, privacy mode, etc.)
                    }
                }
            });

            function setState(open) {
                accordion.classList.toggle('jrps-accordion-open', open);
                accordion.classList.toggle('jrps-accordion-closed', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.hidden = !open;
            }
        });
    });
})();
