function init_index_page() {
    YUI().use('event', function(Y) {
//        Y.on('change', save_status_change, '.statusselect');
    });
}

function init_login_page() {
    document.getElementById('email').focus();
}

function init_edit_players_page() {
    YUI().use('event', function(Y) {
        Y.on('click', onclick_select, 'input');
    });
}

function init_wiki_format_page() {
    YUI().use('event', function(Y) {
        Y.on('click', onclick_select, '#wikimarkup');
    });
    document.getElementById('wikimarkup').select();
}

function onclick_select() {
    this.select();
}

//function save_status_change() {
//    
//}