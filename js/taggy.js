(function (wp) {
    'use strict';
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    function TaggyPanel() {
        var queryState = wp.element.useState(''), query = queryState[0], setQuery = queryState[1];
        var resultsState = wp.element.useState([]), results = resultsState[0], setResults = resultsState[1];
        var statusState = wp.element.useState(''), status = statusState[0], setStatus = statusState[1];
        var busyState = wp.element.useState(false), busy = busyState[0], setBusy = busyState[1];
        wp.element.useEffect(function () {
            var controller = new AbortController();
            var trimmed = query.trim();
            setResults([]);
            setStatus('');
            if (trimmed.length < 3 || !taggyConfig.configured) { return function () { controller.abort(); }; }
            var timer = setTimeout(function () {
                setStatus(__('Searching…', 'taggy'));
                var body = new URLSearchParams({ action: 'taggy_search', nonce: taggyConfig.nonce, query: trimmed });
                fetch(taggyConfig.url, { method: 'POST', credentials: 'same-origin', body: body, signal: controller.signal })
                    .then(function (response) { return response.json(); })
                    .then(function (response) {
                        if (controller.signal.aborted) { return; }
                        if (!response.success) { throw new Error(response.data && response.data.message || __('Search failed.', 'taggy')); }
                        setResults(response.data);
                        setStatus(response.data.length ? '' : __('No movie titles found.', 'taggy'));
                    })
                    .catch(function (error) { if (!controller.signal.aborted) { setStatus(error.message || __('Search failed.', 'taggy')); } });
            }, 500);
            return function () { clearTimeout(timer); controller.abort(); };
        }, [query]);
        function addTag(title) {
            setBusy(true);
            setStatus(__('Adding tag…', 'taggy'));
            wp.apiFetch({ path: '/wp/v2/tags?search=' + encodeURIComponent(title) + '&per_page=100' })
                .then(function (terms) {
                    var existing = terms.find(function (term) { return term.name.toLocaleLowerCase() === title.toLocaleLowerCase(); });
                    return existing || wp.apiFetch({ path: '/wp/v2/tags', method: 'POST', data: { name: title } });
                })
                .then(function (term) {
                    var ids = wp.data.select('core/editor').getEditedPostAttribute('tags') || [];
                    if (ids.indexOf(term.id) === -1) { wp.data.dispatch('core/editor').editPost({ tags: ids.concat(term.id) }); }
                    setStatus(__('Tag added. Save your post to keep it.', 'taggy'));
                    setResults([]);
                })
                .catch(function (error) { setStatus(error.message || __('The tag could not be added.', 'taggy')); })
                .finally(function () { setBusy(false); });
        }
        return el(wp.editPost.PluginDocumentSettingPanel, { name: 'taggy-movies', title: __('Taggy movie tags', 'taggy'), className: 'taggy-movies' },
            taggyConfig.configured ? el(wp.components.TextControl, { label: __('Search movie titles', 'taggy'), value: query, onChange: setQuery, maxLength: 100, help: __('Searches are sent to OMDb after three characters.', 'taggy') }) : el('p', null, __('Ask an administrator to add an OMDb key in Settings → Taggy.', 'taggy')),
            el('div', { role: 'status', 'aria-live': 'polite' }, status),
            el('ul', null, results.map(function (title) {
                return el('li', { key: title }, el(wp.components.Button, { variant: 'link', disabled: busy, onClick: function () { addTag(title); } }, title));
            }))
        );
    }
    wp.plugins.registerPlugin('taggy-movies', { render: TaggyPanel, icon: 'tag' });
})(window.wp);
