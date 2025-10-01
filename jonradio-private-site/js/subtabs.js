/* Subtabs behavior for CMB2 option pages in My Private Site */
(function(){
    var d = document;
    function readData(){
        if (window.jrpsSubtabs) { return window.jrpsSubtabs; }
        return { map: {}, active: '' };
    }
    function applyClasses(map, active){
        var wrap = d.querySelector('.cmb2-wrap');
        if(!wrap) return;
        Object.keys(map || {}).forEach(function(fid){
            var row = wrap.querySelector('.cmb2-id-' + String(fid).replace(/_/g,'-'));
            if(!row) return;
            var slug = map[fid];
            row.classList.add('jrps-subtab-row','jrps-subtab-' + slug);
            var isActive = slug === active;
            row.classList.toggle('jrps-active', isActive);
            row.classList.toggle('jrps-inactive', !isActive);
        });
    }
    function getParam(name){
        var m = new RegExp('[?&]'+name+'=([^&#]*)').exec(window.location.search);
        return m ? decodeURIComponent(m[1].replace(/\+/g,' ')) : '';
    }
    function updateActive(active){
        var wrap = d.querySelector('.cmb2-wrap');
        if(!wrap) return;
        var rows = wrap.querySelectorAll('.jrps-subtab-row');
        [].forEach.call(rows, function(row){
            var isActive = row.classList.contains('jrps-subtab-' + active);
            row.classList.toggle('jrps-active', isActive);
            row.classList.toggle('jrps-inactive', !isActive);
        });
    }
    d.addEventListener('DOMContentLoaded', function(){
        var data = readData();
        var active = data.active || getParam('subtab') || '';
        applyClasses(data.map, active);
        // Watch for manual URL changes (back/forward)
        window.addEventListener('popstate', function(){
            var fromUrl = getParam('subtab');
            if(fromUrl){ active = fromUrl; updateActive(active); }
        });
        var wrap = d.querySelector('.cmb2-wrap');
        if (wrap) { wrap.classList.add('cmb-ready'); }
    });
})();
