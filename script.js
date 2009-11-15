function init_index_page() {
    YUI().use('event', function(Y) {
        // TODO Y.on('click', onclick_select, '#wikimarkup');
    });
}

function init_login_page() {
    document.getElementById('email').focus();
}

function init_recover_tokens_page() {
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