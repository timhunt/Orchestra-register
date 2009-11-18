function init_index_page() {
}

function init_login_page() {
    document.getElementById('email').focus();
}

function init_edit_players_page() {
    YUI().use('event', function(Y) {
        Y.on('click', onclick_select, 'input');
    });
}

function init_edit_player_page() {
    document.getElementById('firstname').focus();
}

function init_edit_events_page() {
}

function init_edit_event_page() {
    document.getElementById('name').focus();
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
