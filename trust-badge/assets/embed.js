(function(){
    'use strict';
    function renderBadge(container){
        var token = container.getAttribute('data-tb-token');
        if(!token){return;}
        var endpoint = (window.TBTrustBadge && TBTrustBadge.endpoint) || (document.currentScript ? document.currentScript.src.replace(/\/assets\/embed\.js.*/, '') + 'wp-json/trustbadge/v1/badge/' : '');
        if(!endpoint){
            endpoint = window.location.origin + '/wp-json/trustbadge/v1/badge/';
        }
        fetch(endpoint + encodeURIComponent(token), {credentials:'omit'})
            .then(function(res){return res.json();})
            .then(function(data){
                if(!data.valid){
                    container.innerHTML = '';
                    return;
                }
                container.innerHTML = data.badge;
            })
            .catch(function(){
                container.innerHTML = '';
            });
    }

    function init(){
        var containers = document.querySelectorAll('[data-tb-token]');
        for(var i=0;i<containers.length;i++){
            renderBadge(containers[i]);
        }
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', init);
    }else{
        init();
    }
})();
