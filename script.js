function init_index_page(eventids, playerids) {
    YUI().use('event', 'ua', function(Y) {
        if (Y.UA.gecko > 0) {
            // There is  a really annoying Firfox bug that stops this from working.
            // The select you are leaving is changes before we get the event, so
            // there is no way to prevent the default action. Therefore, don't add
            // the event handlers.
            return;
        }

        Y.on('keydown', register_keydown, 'select.statusselect', null, eventids, playerids);
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

function init_edit_player_page() {
    document.getElementById('firstname').focus();
}

function init_edit_events_page() {
}

function init_edit_event_page() {
    document.getElementById('name').focus();
}

function init_date_hint(fieldid) {
    YUI().use('event', function(Y) {
        var field = document.getElementById(fieldid);
        var hintspan = document.createElement('span');
        hintspan.className = 'postfix';
        field.parentNode.appendChild(hintspan);
        Y.on('change', date_field_change, field, null, field, hintspan);
        Y.on('keyup', date_field_change, field, null, field, hintspan);
        date_field_change(null, field, hintspan);
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

function date_field_change(e, field, hintspan) {
    var day = 'Not a valid date';
    var date = new Date(field.value);
    if (date.getYear()) {
        day = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()];
    }
    while (hintspan.hasChildNodes()) {
        hintspan.removeChild(hintspan.firstChild);
    }
    hintspan.appendChild(document.createTextNode(' (' + day + ') '));
}

function register_keydown(e, eventids, playerids) {
    switch (e.keyCode) {
    case 38: // Up
        move_register_focus(e.currentTarget.get('id'), -1, 1, playerids);
        break;
    case 40: // Down
        move_register_focus(e.currentTarget.get('id'), 1, 1, playerids);
        break;
    case 37: // Left
        move_register_focus(e.currentTarget.get('id'), -1, 2, eventids);
        break;
    case 39: // Right
        move_register_focus(e.currentTarget.get('id'), 1, 2, eventids);
        break;
    default:
        return;
    }
    e.halt();
}

function move_register_focus(currentid, direction, bit, ids) {
    var bits = currentid.split('_');
    var len = ids.length;
    for (var i = 0; i < len; ++i) {
        if (ids[i] == bits[bit]) {
            break;
        }
    }
    if (i == len) {
        return;
    }
    var iStart = i;
    do {
        i = (i + direction + len) % len;
        bits[bit] = ids[i];
    } while (!document.getElementById(bits.join('_')) && i != iStart);
    document.getElementById(bits.join('_')).focus();
}
