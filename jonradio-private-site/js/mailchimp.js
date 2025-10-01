// Mailchimp inline subscribe for My Private Site admin
(function(){
  function ready(fn){
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function collectParams(inputs){
    var q = [];
    inputs.forEach(function(el){
      if(!el.name || el.disabled) return;
      q.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value || ''));
    });
    return q.join('&');
  }

  ready(function(){
    var container = document.getElementById('mc-embedded-subscribe-form');
    if(!container) return;
    var inputs = container.querySelectorAll('input[type="email"], input[type="text"]');
    var btn = document.getElementById('mc-embedded-subscribe');
    if(!btn) return;

    var localMsg = document.getElementById('mc-local-response');
    if(!localMsg){
      localMsg = document.createElement('div');
      localMsg.id = 'mc-local-response';
      localMsg.setAttribute('aria-live','polite');
      localMsg.setAttribute('role','status');
      btn.parentNode ? btn.parentNode.insertAdjacentElement('afterend', localMsg) : container.appendChild(localMsg);
    }

    function fallbackIframeSubmit(){
      var basePost = 'https://zatzlabs.us10.list-manage.com/subscribe/post?u=81b10c30eeed8b4ec79c86d53&id=f56ca4c04e';
      var frameName = 'mc_iframe_' + Math.random().toString(36).slice(2);
      var iframe = document.createElement('iframe');
      iframe.name = frameName;
      iframe.style.display = 'none';
      document.body.appendChild(iframe);

      var form = document.createElement('form');
      form.method = 'post';
      form.action = basePost;
      form.target = frameName;

      // Copy inputs into hidden fields
      Array.prototype.slice.call(inputs).forEach(function(src){
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = src.name;
        input.value = src.value || '';
        form.appendChild(input);
      });
      // Add honeypot field expected by MC form
      var hp = document.createElement('input');
      hp.type = 'hidden';
      hp.name = 'b_81b10c30eeed8b4ec79c86d53_f56ca4c04e';
      hp.value = '';
      form.appendChild(hp);

      iframe.addEventListener('load', function(){
        if(localMsg){
          localMsg.textContent = 'Submitted. If successful, check your email to confirm.';
          localMsg.classList.remove('is-error');
          localMsg.classList.add('is-success');
        }
        // Cleanup a bit after load
        setTimeout(function(){
          if(form.parentNode) form.parentNode.removeChild(form);
          if(iframe.parentNode) iframe.parentNode.removeChild(iframe);
        }, 1000);
      });

      document.body.appendChild(form);
      try { form.submit(); } catch(e) {}
    }

    btn.addEventListener('click', function(){
      if(localMsg){
        localMsg.classList.remove('is-error','is-success');
        localMsg.textContent = 'Submitting…';
      }

      var base = container.getAttribute('data-action') || 'https://zatzlabs.us10.list-manage.com/subscribe/post-json?u=81b10c30eeed8b4ec79c86d53&id=f56ca4c04e';
      var cbName = '__mc_cb_' + Math.random().toString(36).slice(2);
      var url = base.replace(/&?c=\?/, '') + '&c=' + cbName + '&' + collectParams(Array.prototype.slice.call(inputs));

      window[cbName] = function(resp){
        try {
          if(localMsg){
            var isError = resp && resp.result !== 'success';
            var msg = (resp && resp.msg) ? String(resp.msg) : (isError ? 'Subscription failed.' : 'Thanks, confirmed.');
            // Strip numeric prefixes like "0 - ..."
            msg = msg.replace(/^\d+\s*-\s*/, '');
            localMsg.textContent = msg;
            localMsg.classList.toggle('is-error', isError);
            localMsg.classList.toggle('is-success', !isError);
          }
        } finally {
          if(script && script.parentNode) script.parentNode.removeChild(script);
          try { delete window[cbName]; } catch(e){ window[cbName] = undefined; }
        }
      };

      var script = document.createElement('script');
      script.src = url;
      script.onerror = function(){
        if(localMsg){
          localMsg.textContent = 'Adding subscription';
          localMsg.classList.add('is-error');
        }
        if(script.parentNode) script.parentNode.removeChild(script);
        try { delete window[cbName]; } catch(e){ window[cbName] = undefined; }
        // Fallback via hidden iframe POST
        fallbackIframeSubmit();
      };
      document.body.appendChild(script);
    });
  });
})();

